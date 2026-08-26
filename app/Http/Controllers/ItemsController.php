<?php

namespace App\Http\Controllers;

use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Enums\TransactionType;
use App\Http\Requests\StoreItemRequest;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Report;
use App\Models\Tag;
use App\Models\TransactionDetail;
use App\Models\WarehouseItem;
use App\Services\ItemService;
use App\Services\Items\ItemGroupHierarchyService;
use App\Services\Items\ItemGroupParentExportService;
use App\Services\Items\ItemIdentityBuilder;
use App\Services\Items\LegacyItemConverterService;
use App\Services\ProductPerformanceService;
use App\Services\JubelioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemsController extends Controller
{
    public function __construct(
        protected ItemService $itemService,
        protected ProductPerformanceService $performance,
        protected ItemGroupHierarchyService $groupHierarchy,
        protected ItemIdentityBuilder $identityBuilder,
        protected LegacyItemConverterService $legacyConverter,
    ) {}

    public function index(Request $request, ?ItemType $type = null)
    {
        $type = $type ?? ItemType::ITEM;
        $p = Item::getPermissions();
        $permission = $type === ItemType::ASSET_LANCAR ? $p['asset-lancar-view'] : $p['view'];
        $canViewItems = Gate::check($permission);
        $canExportSellLookup = $this->isJson($request) && Gate::check(Report::getPermissions()['view-export-sell']);

        if (! $canViewItems && ! $canExportSellLookup) {
            Gate::authorize($permission);
        }

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
            if ($request->filled('id') || $request->filled('code')) {
                return $q->with('warehouseItems')->limit(8)->get();
            }

            $search = trim((string) $request->input('search', ''));
            if (strlen($search) <= 2) {
                return response()->json([]);
            }

            return $q->with('warehouseItems')->limit(8)->get();
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

        $items = $q->with('group')
            ->withSum(['warehouseItems as active_qty' => fn ($query) => $query->forActiveWarehouseAddrbooks()], 'quantity')
            ->orderBy('id', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('items.index', [
            'items' => $items,
            'filters' => $request->only(['search', 'brand', 'type', 'jahit', 'size', 'warna', 'item_type', 'code', 'name', 'product_name', 'desc']),
            'brands' => $this->brandOptions(),
            'types' => $this->typeOptions(),
            'tags' => $this->tagGroupsForList($type),
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
                ->forWarehouseAddrbooks(withTrashed: true)
                ->with('warehouse')
                ->orderBy('warehouse_id'),
        ]);

        $activeWarehouseItems = $item->warehouseItems
            ->filter(fn (WarehouseItem $row) => $row->warehouse && ! $row->warehouse->trashed())
            ->values();
        $deletedWarehouseItems = $item->warehouseItems
            ->filter(fn (WarehouseItem $row) => $row->warehouse && $row->warehouse->trashed())
            ->values();

        return view('items.show', [
            'item' => $item,
            'activeWarehouseItems' => $activeWarehouseItems,
            'deletedWarehouseItems' => $deletedWarehouseItems,
            'activeStock' => (float) $activeWarehouseItems->sum('quantity'),
            'deletedStock' => (float) $deletedWarehouseItems->sum('quantity'),
            'isAsset' => $item->type === ItemType::ASSET_LANCAR,
            'groupUrl' => $item->group_id
                ? route('items.group-parent-detail', $this->identityBuilder->parentKeyToSlug(
                    $this->identityBuilder->itemParentKey($item)
                ))
                : null,
            'identityConvert' => $this->detailIdentityConvertContext($item),
        ]);
    }

    protected function detailIdentityConvertContext(Item $item): ?array
    {
        $user = auth()->user();

        if (! $user?->is_superadmin && ! Gate::check(Item::getPermissions()['convert-legacy'])) {
            return null;
        }

        $context = $this->legacyConverter->detailConvertContext($item);

        return $context['visible'] ? $context : null;
    }

    public function edit(Item $item)
    {
        $p = Item::getPermissions();
        Gate::authorize($item->type === ItemType::ASSET_LANCAR ? $p['asset-lancar-edit'] : $p['edit']);

        return view('items.edit', array_merge($this->formProps($item->type), [
            'item' => $item->load(['group', 'tags']),
            'types' => $this->typeOptions(),
        ]));
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

        try {
            $response = $jubelioService->get('https://api2.jubelio.com/inventory/items/to-stock/', [
                'q' => $request->input('q', $item->code),
            ]);

            if (! $response) {
                return back()->withErrors(['message' => 'Gagal otentikasi Jubelio']);
            }

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

        $filters = $request->only(['kode', 'product_name', 'desc']);

        return view('items.group', [
            'parents' => $this->groupHierarchy->paginateParents($filters, 20),
            'filters' => $filters,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function groupParentDetail(string $parentSlug)
    {
        Gate::authorize(ItemGroup::getPermissions()['view']);

        $parentKey = $this->identityBuilder->parentKeyFromSlug($parentSlug);
        $detail = $this->groupHierarchy->parentDetail($parentKey);

        abort_if($detail === null, 404);

        return view('items.group-parent-detail', [
            'detail' => $detail,
            'canEditGroup' => auth()->user()->can(ItemGroup::getPermissions()['edit']),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function exportGroupParent(string $parentSlug, ItemGroupParentExportService $exportService)
    {
        Gate::authorize(ItemGroup::getPermissions()['view']);

        $parentKey = $this->identityBuilder->parentKeyFromSlug($parentSlug);

        return $exportService->download($parentKey);
    }

    public function updateGroupParent(Request $request, string $parentSlug)
    {
        Gate::authorize(ItemGroup::getPermissions()['edit']);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Product name is required.',
        ]);

        $parentKey = $this->identityBuilder->parentKeyFromSlug($parentSlug);
        $detail = $this->groupHierarchy->parentDetail($parentKey, fetchJubelio: false);

        abort_if($detail === null, 404);

        try {
            foreach ($detail['group_ids'] as $groupId) {
                $group = ItemGroup::findOrFail($groupId);
                $this->itemService->renameGroupProductName($group, $request->input('name'));
            }

            return redirect()
                ->route('items.group-parent-detail', $parentSlug)
                ->with('success', 'Product name updated for all colors in this group.');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()])->withInput();
        }
    }

    public function groupDetail(ItemGroup $group)
    {
        Gate::authorize(ItemGroup::getPermissions()['view']);

        $group->load(['items.tags']);
        $sample = $group->items->first();

        abort_if($sample === null, 404);

        $slug = $this->identityBuilder->parentKeyToSlug(
            $this->identityBuilder->itemParentKey($sample)
        );

        return redirect()->route('items.group-parent-detail', $slug);
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

            $sample = $group->items()->with('tags')->first();
            $redirect = $sample
                ? route('items.group-parent-detail', $this->identityBuilder->parentKeyToSlug(
                    $this->identityBuilder->itemParentKey($sample)
                ))
                : route('items.group');

            return redirect()
                ->to($redirect)
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
            ->visibleToUser($request->user())
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
        $p = Item::getPermissions();
        Gate::authorize($item->type === ItemType::ASSET_LANCAR ? $p['asset-lancar-view'] : $p['view']);

        $periodDays = $this->performance->normalizePeriodDays($request->query('period', 90));
        $warehouseId = $request->query('warehouse_id') ? (int) $request->query('warehouse_id') : null;
        $stats = $this->performance->itemMonthlyBreakdown($item->id, $periodDays, $warehouseId);

        return view('items.item-stats', [
            'item' => $item->load('group'),
            'months' => $stats['months'],
            'totals' => $stats['totals'],
            'syncedAt' => $stats['synced_at'],
            'stale' => $stats['stale'],
            'hasData' => $stats['has_data'],
            'warehouses' => $this->performance->warehouses(),
            'periodOptions' => ProductPerformanceService::periodOptions(),
            'filters' => [
                'period' => $periodDays,
                'warehouse_id' => $warehouseId,
            ],
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
            'jahitTags' => Tag::tagsForItemForm($t, Tag::TYPE_JAHIT),
            'typeTags' => Tag::typeTagsForItem($t),
            'sizeTags' => Tag::tagsForItemForm($t, Tag::TYPE_SIZE),
            'warnaTags' => Tag::tagsForItemForm($t, Tag::TYPE_WARNA),
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

    private function tagGroupsForList(ItemType $itemType): \Illuminate\Support\Collection
    {
        $tags = Tag::all()->groupBy('type');
        $tags[Tag::TYPE_TYPE] = Tag::typeTagsForItem($itemType);

        return $tags;
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

        try {
            $r = $s->post('https://api2.jubelio.com/inventory/items/all-stocks/', [
                'ids' => [$item->jubelio_item_id],
            ]);

            if (! $r) {
                return ['Gagal otentikasi', []];
            }

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
