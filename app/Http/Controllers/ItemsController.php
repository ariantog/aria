<?php

namespace App\Http\Controllers;

use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Http\Requests\StoreItemRequest;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Report;
use App\Models\Tag;
use App\Models\TransactionDetail;
use App\Services\ItemAvailabilityService;
use App\Services\ItemListFilter;
use App\Services\Items\ItemGroupHierarchyService;
use App\Services\Items\ItemGroupParentExportService;
use App\Services\Items\ItemIdentityBuilder;
use App\Services\Items\LegacyItemConverterService;
use App\Services\ItemService;
use App\Services\ItemStatsService;
use App\Services\ItemTransactionQueryService;
use App\Services\JubelioService;
use App\Support\LikeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemsController extends Controller
{
    public function __construct(
        protected ItemService $itemService,
        protected ItemStatsService $itemStats,
        protected ItemGroupHierarchyService $groupHierarchy,
        protected ItemIdentityBuilder $identityBuilder,
        protected LegacyItemConverterService $legacyConverter,
        protected ItemListFilter $itemListFilter,
        protected ItemAvailabilityService $itemAvailability,
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
            ->when($request->filled('brand'), fn ($q) => $q->filterBrand((int) $request->brand));

        if (! $this->isJson($request)) {
            $this->itemListFilter->apply($q, $request);
        }

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
                    'description' => $item->catalogDescription(),
                    'description2' => $item->catalogDescription2(),
                ])->all(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ]);
        }

        $items = $q->with(['group', 'tags'])
            ->withSum(['warehouseItems as active_qty' => fn ($query) => $query->forAvailableStock()], 'quantity')
            ->orderBy('id', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('items.index', [
            'items' => $items,
            'filters' => $request->only(array_merge($this->itemListFilter->filterKeys(), ['search', 'brand', 'type'])),
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

        $stock = $this->itemAvailability->partitionWarehouseItems($item->warehouseItems);

        return view('items.show', [
            'item' => $item,
            'activeWarehouseItems' => $stock['physical'],
            'virtualWarehouseItems' => $stock['virtual'],
            'deletedWarehouseItems' => $stock['deleted'],
            'activeStock' => $stock['available'],
            'virtualStock' => $stock['virtual_stock'],
            'deletedStock' => $stock['deleted_stock'],
            'canRecalculateQty' => $this->canRecalculateQty($item),
            'isAsset' => $item->type === ItemType::ASSET_LANCAR,
            'groupUrl' => $this->legacyConverter->hasProductGroup($item)
                ? route('items.group-parent-detail', $this->identityBuilder->parentKeyToSlug(
                    $this->identityBuilder->itemParentKey($item)
                ))
                : null,
            'identityConvert' => $this->detailIdentityConvertContext($item),
        ]);
    }

    public function recalculateQuantity(Item $item)
    {
        Gate::authorize($item->type === ItemType::ASSET_LANCAR
            ? Item::getPermissions()['asset-lancar-edit']
            : Item::getPermissions()['edit']
        );

        $result = $this->itemAvailability->recalculate($item);
        $available = format_amount($result['available'], 0);

        return redirect($item->showUrl())->with(
            'success',
            "Quantity recalculated. Available stock is {$available} units (non-deleted warehouses, virtual excluded).",
        );
    }

    protected function canRecalculateQty(Item $item): bool
    {
        $permission = $item->type === ItemType::ASSET_LANCAR
            ? Item::getPermissions()['asset-lancar-edit']
            : Item::getPermissions()['edit'];

        return Gate::check($permission);
    }

    protected function detailIdentityConvertContext(Item $item): ?array
    {
        $user = auth()->user();
        $permissions = Item::getPermissions();
        $viewPermission = $item->type === ItemType::ASSET_LANCAR
            ? $permissions['asset-lancar-view']
            : $permissions['view'];

        if (! $user?->is_superadmin && ! Gate::check($viewPermission)) {
            return null;
        }

        $context = $this->legacyConverter->detailConvertContext($item);

        if (! $context['visible']) {
            return null;
        }

        $canConvert = $user?->is_superadmin || Gate::check($permissions['convert-legacy']);
        $context['can_convert'] = $canConvert;

        if (! $canConvert) {
            $context['convertible'] = false;
            $context['message'] = trim(($context['message'] ?? '').' Legacy Converter permission is required to run conversion.');
        }

        return $context;
    }

    public function edit(Item $item)
    {
        $p = Item::getPermissions();
        Gate::authorize($item->type === ItemType::ASSET_LANCAR ? $p['asset-lancar-edit'] : $p['edit']);

        $item->load(['group', 'tags']);
        $productTitle = $this->identityBuilder->productDisplayName(
            $item->type,
            (string) ($item->group?->name ?: $item->name),
            (string) ($item->group?->variant ?? ''),
            (string) ($item->group?->master ?? ''),
        );

        return view('items.edit', array_merge($this->formProps($item->type), [
            'item' => $item,
            'types' => $this->typeOptions(),
            'productTitle' => $productTitle,
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
            'description' => ['nullable', 'string'],
            'description2' => ['nullable', 'string'],
            'url' => ['nullable', 'string', 'max:255'],
            'restock_urgent_threshold' => ['nullable', 'integer', 'min:1'],
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

            return redirect($item->fresh()->showUrl())->with('success', 'Item updated.');
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

    public function itemTransactions(Request $request, Item $item, ItemTransactionQueryService $queryService)
    {
        Gate::authorize(Item::getPermissions()['view']);

        $filters = $queryService->filtersFromRequest($request);
        $isAsset = $item->type === ItemType::ASSET_LANCAR;
        $formAction = $isAsset
            ? route('assetlancar.transactions', $item)
            : route('items.transactions', $item);

        $transactions = $queryService->apply(
            TransactionDetail::with(['transaction.sender', 'transaction.receiver'])
                ->where('item_id', $item->id)
                ->visibleToUser($request->user())
                ->whereHas('transaction'),
            $request,
        )
            ->paginate(50)
            ->withQueryString();

        return view('items.item-transactions', [
            'item' => $item->load('group'),
            'transactions' => $transactions,
            'isAsset' => $isAsset,
            'filters' => $filters,
            'formAction' => $formAction,
            'resetUrl' => $formAction,
            'partyLookupUrl' => route('items.party-lookup'),
            'selectedParty' => $queryService->resolveSelectedParty($filters['party'], $request->user()),
            'hasActiveFilters' => $queryService->hasActiveFilters($filters),
        ]);
    }

    public function pcodeName(Request $request)
    {
        $type = ItemType::tryFrom((int) $request->query('type', ItemType::ITEM->value))
            ?? ItemType::ITEM;
        $permissions = Item::getPermissions();
        $create = $type === ItemType::ASSET_LANCAR
            ? $permissions['asset-lancar-create']
            : $permissions['create'];
        $edit = $type === ItemType::ASSET_LANCAR
            ? $permissions['asset-lancar-edit']
            : $permissions['edit'];

        abort_unless(Gate::check($create) || Gate::check($edit), 403);

        $pcode = strtoupper(trim((string) $request->query('pcode', '')));
        $productName = $this->itemService->productNameForPcode($type, $pcode);

        return response()->json([
            'pcode' => $pcode,
            'product_name' => $productName,
            'found' => $productName !== null,
        ]);
    }

    public function partyLookup(Request $request)
    {
        $permissions = Item::getPermissions();
        abort_unless(
            Gate::check($permissions['view']) || Gate::check($permissions['asset-lancar-view']),
            403
        );

        $search = trim((string) $request->query('search', ''));
        if (strlen($search) <= 2) {
            return response()->json([]);
        }

        $pattern = LikeSearch::contains($search);
        $results = Addrbook::query()
            ->visibleToUser($request->user())
            ->whereIn('customers.type', Addrbook::itemTransactionPartyTypes())
            ->where(function ($q) use ($pattern) {
                $q->where('customers.name', 'like', $pattern)
                    ->orWhere('customers.id', 'like', $pattern);
            })
            ->leftJoin('customerstat', 'customers.id', '=', 'customerstat.customer_id')
            ->select(
                'customers.id',
                'customers.name',
                'customers.ppn',
                'customers.type',
                'customers.ledger_hint',
                'customerstat.balance'
            )
            ->orderBy('customers.name')
            ->limit(8)
            ->get();

        return response()->json($results);
    }

    public function itemStats(Request $request, Item $item)
    {
        $p = Item::getPermissions();
        Gate::authorize($item->type === ItemType::ASSET_LANCAR ? $p['asset-lancar-view'] : $p['view']);

        $periodDays = $this->itemStats->normalizePeriodDays($request->query('period', 90));
        $warehouseId = $request->query('warehouse_id') ? (int) $request->query('warehouse_id') : null;
        $stats = $this->itemStats->monthlyBreakdown($item->id, $periodDays, $warehouseId);

        return view('items.item-stats', [
            'item' => $item->load('group'),
            'months' => $stats['months'],
            'totals' => $stats['totals'],
            'hasData' => $stats['has_data'],
            'warehouses' => $this->itemStats->warehouses(),
            'periodOptions' => ItemStatsService::periodOptions(),
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
