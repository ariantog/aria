<?php

namespace App\Http\Controllers;

use App\Enums\AddrbookType;
use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Enums\TransactionType;
use App\Http\Requests\StoreItemRequest;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\TransactionDetail;
use App\Services\ItemService;
use App\Services\JubelioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemsController extends Controller
{
    public function __construct(protected ItemService $itemService) {}

    public function index(Request $request, ?ItemType $type = null)
    {
        $type = $type ?? ItemType::ITEM;
        $p = Item::getPermissions();
        Gate::authorize($type === ItemType::ASSET_LANCAR ? $p['asset-lancar-view'] : $p['view']);

        $q = Item::with('group');
        if ($this->isJson($request)) {
            if ($request->filled('type')) {
                $q->whereIn('type', array_map('intval', explode(',', $request->input('type'))));
            }
        } else {
            $q->where('type', $type);
        }

        $q->when($request->filled('search'), fn ($q) => $q->search($request->search))
            ->when($request->filled('id'), fn ($q) => $q->where('id', $request->id))
            ->when($request->filled('brand'), fn ($q) => $q->where('brand', $request->brand))
            ->when($request->filled('code'), fn ($q) => $q->where('code', 'like', "{$request->code}%"))
            ->when($request->filled('name'), fn ($q) => $q->where('name', 'like', "%{$request->name}%"))
            ->when($request->filled('product_name'), fn ($q) => $q->whereHas('group', fn ($s) => $s->where('name', 'like', "%{$request->product_name}%")))
            ->when($request->filled('desc'), fn ($q) => $q->where(fn ($s) => $s
                ->where('description', 'like', "%{$request->desc}%")
                ->orWhereHas('group', fn ($g) => $g->where('description', 'like', "%{$request->desc}%"))
            ));

        $this->applyTagFilters($q, $request);

        // Combobox / autocomplete JSON (unpaginated, limited) — used by asyncCombobox.
        if ($this->isJson($request) && ! $request->boolean('table')) {
            return $q->with('warehouseItems')->limit(50)->get();
        }

        // Tabulator remote pagination JSON.
        if ($request->expectsJson() || $request->ajax()) {
            $sortField = $request->input('sort.0.field', 'id');
            $sortDir = $request->input('sort.0.dir', 'desc');
            $allowedSorts = ['id', 'code', 'pcode', 'name', 'price', 'qty'];
            if (! in_array($sortField, $allowedSorts, true)) {
                $sortField = 'id';
            }

            $perPage = (int) $request->input('size', 50);
            $paginator = $q->with('group')
                ->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc')
                ->paginate($perPage);

            return response()->json([
                'data' => collect($paginator->items())->map(fn ($item) => [
                    'id' => $item->id,
                    'code' => $item->code,
                    'pcode' => $item->pcode,
                    'name' => $item->name,
                    'price' => $item->price,
                    'qty' => $item->qty,
                    'image_url' => $item->image_url,
                    'jubelio_item_id' => $item->jubelio_item_id,
                    'product_name' => $item->group?->name ?? $item->name,
                    'description' => $item->group?->description ?? $item->description,
                    'description2' => $item->group?->description2 ?? $item->description2,
                ])->all(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ]);
        }

        $items = $q->with('group')->orderBy('id', 'desc')->paginate(50)->withQueryString();

        return view('items.index', [
            'items' => $items,
            'filters' => $request->only(['search', 'brand', 'type', 'jahit', 'size', 'warna', 'item_type', 'code', 'name', 'product_name', 'desc']),
            'brands' => $this->brandOptions(),
            'types' => $this->typeOptions(),
            'tags' => \App\Models\Tag::all()->groupBy('type'),
            'can' => $this->itemPermissions($type),
            'isAsset' => $type === ItemType::ASSET_LANCAR,
            'baseUrl' => $type === ItemType::ASSET_LANCAR ? '/assetlancar' : '/items',
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function indexAsset(Request $request)
    {
        return $this->index($request, ItemType::ASSET_LANCAR);
    }

    public function create()
    {
        Gate::authorize(Item::getPermissions()['create']);

        return view('items.create', $this->formProps(ItemType::ITEM));
    }

    public function createAsset()
    {
        Gate::authorize(Item::getPermissions()['asset-lancar-create']);

        return view('items.create', $this->formProps(ItemType::ASSET_LANCAR));
    }

    public function store(StoreItemRequest $request)
    {
        $type = ItemType::from((int) $request->input('type'));
        Gate::authorize($type === ItemType::ASSET_LANCAR
            ? Item::getPermissions()['asset-lancar-create']
            : Item::getPermissions()['create']
        );

        try {
            $this->itemService->create(
                (object) $request->except(['image', 'tags']),
                $request->input('tags', []),
                $request->file('image')
            );

            $route = $type === ItemType::ASSET_LANCAR ? 'assetlancar.index' : 'items.index';

            return redirect()->route($route)->with('success', 'Item created.');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()])->withInput();
        }
    }

    public function show(Item $item)
    {
        $item->load([
            'group',
            'tags',
            'warehouseItems' => fn ($q) => $q
                ->whereIn('warehouse_id', fn ($s) => $s->select('id')->from('addrbooks')->where('type', AddrbookType::Warehouse->value))
                ->with('warehouse'),
        ]);

        return view('items.show', [
            'item' => $item,
            'isAsset' => $item->type === ItemType::ASSET_LANCAR,
        ]);
    }

    public function edit(Item $item)
    {
        $p = Item::getPermissions();
        Gate::authorize($item->type === ItemType::ASSET_LANCAR ? $p['asset-lancar-edit'] : $p['edit']);

        return view('items.edit', [
            'item' => $item->load(['group', 'tags']),
            'brands' => $this->brandOptions(),
            'types' => $this->typeOptions(),
            'tags' => \App\Models\Tag::all()->groupBy('type'),
            'isAsset' => $item->type === ItemType::ASSET_LANCAR,
        ]);
    }

    public function update(Request $request, Item $item)
    {
        $p = Item::getPermissions();
        Gate::authorize($item->type === ItemType::ASSET_LANCAR ? $p['asset-lancar-edit'] : $p['edit']);

        $isAsset = $item->type === ItemType::ASSET_LANCAR;

        $request->validate([
            'pcode' => ['required', 'string'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric'],
            'cost' => $isAsset ? ['required', 'numeric'] : ['nullable'],
            'tags.types' => $isAsset ? ['nullable'] : ['required'],
            'tags.sizes' => ['required'],
            'tags.warna' => ['required'],
            'tags.jahit' => $isAsset ? ['nullable'] : ['required'],
        ], [
            'product_name.required' => 'Product name is required.',
            'tags.warna.required' => 'Please select a color (warna).',
            'tags.types.required' => 'Please select a type (SKU prefix).',
            'tags.jahit.required' => 'Please select a jahit tag.',
        ]);

        try {
            $this->itemService->update(
                $item->id,
                (object) $request->except(['image', 'tags']),
                $request->input('tags', []),
                $request->file('image')
            );

            $route = $isAsset ? 'assetlancar.index' : 'items.index';

            return redirect()->route($route)->with('success', 'Item updated.');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(Item $item)
    {
        $p = Item::getPermissions();
        Gate::authorize($item->type === ItemType::ASSET_LANCAR ? $p['asset-lancar-delete'] : $p['delete']);
        $item->delete();

        return redirect()->route('items.index')->with('success', 'Item deleted.');
    }

    public function jubelio(Item $item, JubelioService $s)
    {
        Gate::authorize(Item::getPermissions()['view']);
        $item->load(['group', 'tags']);
        [$msg, $data] = $this->fetchJubelio($item, $s);

        return view('items.jubelio', [
            'item' => $item,
            'dataJubelio' => $data,
            'message' => $msg,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function getJubelioItems(Item $item, JubelioService $jubelioService, Request $request)
    {
        Gate::authorize(Item::getPermissions()['edit']);

        $token = $jubelioService->getCachedToken();
        if (! $token) {
            return back()->withErrors(['message' => 'Gagal otentikasi Jubelio']);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Content-Type' => 'application/json',
                'authorization' => $token,
            ])->get('https://api2.jubelio.com/inventory/items/to-stock/', [
                'q' => $request->input('q', $item->code),
            ]);

            if ($response->successful()) {
                return view('items.jubelio-search', [
                    'item' => $item,
                    'jubelioItems' => $response->json()['data'] ?? [],
                    'query' => $request->input('q', $item->code),
                ]);
            }

            return back()->withErrors(['message' => 'Gagal mengambil daftar item dari Jubelio']);
        } catch (\Exception $e) {
            return back()->withErrors(['message' => 'Error: '.$e->getMessage()]);
        }
    }

    public function updateJubelioId(Item $item, Request $r)
    {
        Gate::authorize(Item::getPermissions()['edit']);
        $r->validate(['jubelio_item_id' => 'nullable|integer']);
        $item->update(['jubelio_item_id' => $r->jubelio_item_id]);

        return redirect()->route('items.jubelio', $item->id)->with('success', 'Koneksi Jubelio diperbarui');
    }

    public function group(Request $request)
    {
        Gate::authorize(ItemGroup::getPermissions()['view']);

        $query = ItemGroup::query()
            ->when($request->filled('kode'), fn ($q) => $q->where('name', 'like', "%{$request->kode}%"))
            ->when($request->filled('product_name'), fn ($q) => $q->where('name', 'like', "%{$request->product_name}%"))
            ->when($request->filled('desc'), fn ($q) => $q->where('description', 'like', "%{$request->desc}%"));

        return view('items.group', [
            'groups' => $query->orderBy('id', 'desc')->paginate(20)->withQueryString(),
            'filters' => $request->only(['kode', 'product_name', 'desc']),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function groupDetail(ItemGroup $group)
    {
        Gate::authorize(ItemGroup::getPermissions()['view']);

        $group->load(['items.warehouseItems' => fn ($q) => $q
            ->whereIn('warehouse_id', fn ($sq) => $sq->select('id')->from('addrbooks')->where('type', AddrbookType::Warehouse->value))
            ->with('warehouse'),
        ]);

        $sampleItem = $group->items->first();
        $isManufacturedGroup = $sampleItem && $sampleItem->type === ItemType::ITEM;
        $pcode = $sampleItem?->pcode ?? '';
        $usesPlaceholder = $isManufacturedGroup
            && $pcode !== ''
            && strtoupper($group->name) === strtoupper($pcode);

        return view('items.group-detail', [
            'group' => $group,
            'pcode' => $pcode,
            'usesPlaceholder' => $usesPlaceholder,
            'canEditGroup' => auth()->user()->can(ItemGroup::getPermissions()['edit']),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function updateGroup(Request $request, ItemGroup $group)
    {
        Gate::authorize(ItemGroup::getPermissions()['edit']);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Product name is required.',
        ]);

        try {
            $this->itemService->renameGroupProductName($group, $request->input('name'));

            return redirect()
                ->route('items.group-detail', $group->id)
                ->with('success', 'Product name updated for all items in this group.');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()])->withInput();
        }
    }

    public function groupStats(Request $request, ItemGroup $group)
    {
        Gate::authorize(ItemGroup::getPermissions()['view']);

        $from = $request->input('from', now()->subMonths(11)->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());

        $data = \App\Models\StatSell::select([
            'type as transaction_type',
            \DB::raw("DATE_FORMAT(STR_TO_DATE(CONCAT(tahun, '-', bulan, '-01'), '%Y-%m-%d'), '%M %Y') as showdate"),
            'bulan', 'tahun',
            \DB::raw('SUM(sum_qty) as total_qty'),
        ])
            ->where('group_id', $group->id)
            ->where(\DB::raw('tahun * 100 + bulan'), '>=', \DB::raw('YEAR("'.$from.'") * 100 + MONTH("'.$from.'")'))
            ->where(\DB::raw('tahun * 100 + bulan'), '<=', \DB::raw('YEAR("'.$to.'") * 100 + MONTH("'.$to.'")'))
            ->groupBy('showdate', 'type', 'bulan', 'tahun')
            ->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc')
            ->get();

        return view('items.group-stats', [
            'group' => $group,
            'data' => $data,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function itemTransactions(Request $request, Item $item)
    {
        Gate::authorize(Item::getPermissions()['view']);

        $transactions = TransactionDetail::with(['transaction.sender', 'transaction.receiver'])
            ->where('item_id', $item->id)
            ->whereHas('transaction')
            ->orderBy('transaction_id', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('items.item-transactions', [
            'item' => $item->load('group'),
            'transactions' => $transactions,
            'isAsset' => $item->type === ItemType::ASSET_LANCAR,
        ]);
    }

    public function itemStats(Request $request, Item $item)
    {
        Gate::authorize(Item::getPermissions()['view']);

        $from = $request->input('from');
        $to = $request->input('to');
        $addrId = $request->input('addr');

        $query = TransactionDetail::select([
            'transaction_type',
            \DB::raw("DATE_FORMAT(date,'%M %Y') AS showdate"),
            \DB::raw("DATE_FORMAT(date,'%m') AS bulan"),
            \DB::raw("DATE_FORMAT(date,'%Y') AS tahun"),
            \DB::raw('SUM(quantity) as total_qty'),
        ])
            ->where('item_id', $item->id)
            ->whereIn('transaction_type', [
                TransactionType::Sell->value,
                TransactionType::Move->value,
                TransactionType::Return->value,
                TransactionType::Production->value,
            ])
            ->groupBy('showdate', 'transaction_type', 'bulan', 'tahun')
            ->orderBy('tahun', 'DESC')
            ->orderBy('bulan', 'DESC')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->when($addrId, fn ($q) => $q->where(fn ($sq) => $sq
                ->where('sender_id', $addrId)
                ->orWhere('receiver_id', $addrId)
            ));

        $addrbooks = \App\Models\Addrbook::whereIn('type', [
            AddrbookType::Customer->value,
            AddrbookType::Reseller->value,
            AddrbookType::Warehouse->value,
        ])->orderBy('name')->get(['id', 'name', 'type']);

        return view('items.item-stats', [
            'item' => $item->load('group'),
            'data' => $query->get(),
            'addrbooks' => $addrbooks,
            'filters' => compact('from', 'to') + ['addr' => $addrId],
            'isAsset' => $item->type === ItemType::ASSET_LANCAR,
        ]);
    }

    private function isJson(Request $r): bool
    {
        return ($r->wantsJson() || $r->has('json')) && ! $r->header('X-Inertia');
    }

    private function applyTagFilters($q, Request $r): void
    {
        $tags = collect([
            $r->input('jahit'),
            $r->input('size'),
            $r->input('warna'),
            $r->input('item_type'),
        ])->flatten()->filter()->toArray();

        $explicit = $r->input('tag_ids', []);
        if (is_array($explicit)) {
            $tags = array_unique(array_merge($tags, $explicit));
        }

        if (! empty($tags)) {
            $q->filterByTags($tags);
        }
    }

    private function formProps(ItemType $t): array
    {
        $isAsset = $t === ItemType::ASSET_LANCAR;

        return [
            'brands' => $this->brandOptions(),
            'jahitTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_JAHIT)->get(),
            'typeTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_TYPE)->get(),
            'sizeTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_SIZE)->get(),
            'warnaTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_WARNA)->get(),
            'itemType' => $t->value,
            'isAsset' => $isAsset,
            'assetPcodeSuggestions' => $isAsset
                ? Item::query()
                    ->where('type', ItemType::ASSET_LANCAR)
                    ->whereNotNull('pcode')
                    ->distinct()
                    ->orderBy('pcode')
                    ->limit(100)
                    ->pluck('pcode')
                : collect(),
        ];
    }

    private function brandOptions(): array
    {
        return collect(ItemBrand::cases())->map(fn ($b) => ['value' => $b->value, 'label' => $b->label()])->values()->all();
    }

    private function typeOptions(): array
    {
        return collect(ItemType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->values()->all();
    }

    private function itemPermissions(ItemType $t): array
    {
        $u = auth()->user();
        $p = Item::getPermissions();

        return [
            'create' => $u->can($p['create']),
            'create_asset' => $u->can($p['asset-lancar-create']),
            'edit' => $u->can($p['edit']),
            'edit_asset' => $u->can($p['asset-lancar-edit']),
            'delete' => $u->can($p['delete']),
            'delete_asset' => $u->can($p['asset-lancar-delete']),
        ];
    }

    private function fetchJubelio(Item $item, JubelioService $s): array
    {
        if ($item->jubelio_item_id <= 0) {
            return ['Item belum terhubung', []];
        }

        $t = $s->getCachedToken();
        if (! $t) {
            return ['Gagal otentikasi', []];
        }

        try {
            $r = \Illuminate\Support\Facades\Http::withHeaders([
                'Content-Type' => 'application/json',
                'authorization' => $t,
            ])->post('https://api2.jubelio.com/inventory/items/all-stocks/', [
                'ids' => [$item->jubelio_item_id],
            ]);

            if ($r->successful()) {
                $j = $r->json();

                return empty($j['data']) ? ['Item tidak ditemukan', []] : ['ok', $j['data'][0]];
            }

            return ['Gagal: '.$r->status(), []];
        } catch (\Exception $e) {
            return ['Error: '.$e->getMessage(), []];
        }
    }
}
