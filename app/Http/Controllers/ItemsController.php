<?php

namespace App\Http\Controllers;

use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\TransactionDetail;
use App\Services\ItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ItemsController extends Controller
{
    public function __construct(protected ItemService $itemService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ItemType $type = ItemType::ITEM)
    {
        $permissions = Item::getPermissions();
        if ($type === ItemType::ASSET_LANCAR) {
            Gate::authorize($permissions['asset_lancar_view']);
        } else {
            Gate::authorize($permissions['view']);
        }

        $query = Item::with('group');

        // If JSON request and type is provided specifically as query param (e.g., type=1,2)
        if ($request->filled('type') && ($request->wantsJson() || $request->has('json'))) {
            $types = explode(',', $request->input('type'));
            $query->whereIn('type', $types);
        } else {
            // Default behavior for normal web requests
            $query->where('type', $type);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }

        if ($request->filled('code')) {
            $query->where('code', 'like', "{$request->code}%");
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', "%{$request->name}%");
        }

        if ($request->filled('alias')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('group', function ($sq) use ($request) {
                    $sq->where('alias', 'like', "%{$request->alias}%");
                });
            });
        }

        if ($request->filled('desc')) {
            $query->where(function ($q) use ($request) {
                $q->where('description', 'like', "%{$request->desc}%")
                    ->orWhereHas('group', function ($sq) use ($request) {
                        $sq->where('description', 'like', "%{$request->desc}%");
                    });
            });
        }

        // Handle specific tag filters from legacy or generic tag_ids array
        $tagIds = collect([
            $request->input('jahit'),
            $request->input('size'),
            $request->input('warna'),
            $request->input('item_type'),
        ])->flatten()->filter()->toArray();

        // Merge with any explicit tag_ids array from request
        $explicitTagIds = $request->input('tag_ids', []);
        if (is_array($explicitTagIds)) {
            $tagIds = array_unique(array_merge($tagIds, $explicitTagIds));
        }

        if (! empty($tagIds)) {
            $query->filterByTags($tagIds);
        }

        // JSON Response for Async Select
        if ($request->wantsJson() || $request->has('json')) {
            return $query->with('warehouseItems')->limit(20)->get(['id', 'code', 'name', 'price', 'cost']); // optimized select
        }

        $items = $query->orderBy('id', 'desc')->paginate(50)->withQueryString();

        return Inertia::render('Items/Index', [
            'items' => $items,
            'filters' => $request->only(['search', 'brand', 'type', 'jahit', 'size', 'warna', 'item_type', 'code', 'name', 'alias', 'desc']),
            'brands' => collect(ItemBrand::cases())->map(fn ($b) => ['value' => $b->value, 'label' => $b->label()]),
            'types' => collect(ItemType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'tags' => \App\Models\Tag::all()->groupBy('type'),
            'can' => [
                'create' => auth()->user()->can($permissions['create']),
                'create_asset' => auth()->user()->can($permissions['asset_lancar_create']),
                'edit' => auth()->user()->can($permissions['edit']),
                'edit_asset' => auth()->user()->can($permissions['asset_lancar_edit']),
                'delete' => auth()->user()->can($permissions['delete']),
                'delete_asset' => auth()->user()->can($permissions['asset_lancar_delete']),
            ],
        ]);
    }

    public function indexAsset(Request $request)
    {
        return $this->index($request, ItemType::ASSET_LANCAR);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize(Item::getPermissions()['create']);

        return Inertia::render('Items/Create', [
            'brands' => collect(ItemBrand::cases())->map(fn ($b) => ['value' => $b->value, 'label' => $b->label()]),
            'jahitTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_JAHIT)->get(),
            'typeTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_TYPE)->get(),
            'sizeTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_SIZE)->get(),
            'warnaTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_WARNA)->get(),
            'itemType' => \App\Enums\ItemType::ITEM->value, // Explicitly 1
        ]);
    }

    public function createAsset()
    {
        Gate::authorize(Item::getPermissions()['asset_lancar_create']);

        return Inertia::render('Items/Create', [
            'brands' => collect(ItemBrand::cases())->map(fn ($b) => ['value' => $b->value, 'label' => $b->label()]),
            'jahitTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_JAHIT)->get(),
            'typeTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_TYPE)->get(),
            'sizeTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_SIZE)->get(),
            'warnaTags' => \App\Models\Tag::where('type', \App\Models\Tag::TYPE_WARNA)->get(),
            'itemType' => \App\Enums\ItemType::ASSET_LANCAR->value, // 2
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->input('type') == \App\Enums\ItemType::ASSET_LANCAR->value) {
            Gate::authorize(Item::getPermissions()['asset_lancar_create']);
        } else {
            Gate::authorize(Item::getPermissions()['create']);
        }

        $rules = [
            'pcode' => 'required|string',
            'type' => 'required|integer',
            'price' => 'nullable|numeric',
            'description' => 'nullable|string',
            'description2' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'tags.types' => 'required', // Tag Type (Genre)
            'tags.sizes' => 'required|array',
        ];

        // Conditional Rules
        if ($request->input('type') == \App\Enums\ItemType::ASSET_LANCAR->value) {
            $rules['name'] = 'required|string';
            $rules['cost'] = 'required|numeric';
            $rules['tags.warna'] = 'required'; // Asset needs Warna
            $rules['tags.jahit'] = 'nullable';
        } else {
            // Default ITEM
            $rules['alias'] = 'nullable|string';
            $rules['tags.jahit'] = 'required'; // User listed Jahit for Item
            // User didn't list Warna for Item, so maybe nullable/ignored
            $rules['tags.warna'] = 'nullable';
        }

        $request->validate($rules);

        try {
            $this->itemService->create(
                (object) $request->except(['image', 'tags']),
                $request->input('tags', []),
                $request->file('image')
            );

            if ($request->input('type') == \App\Enums\ItemType::ASSET_LANCAR->value) {
                return redirect()->route('assetlancar.index')->with('success', 'Asset created successfully.');
            }

            return redirect()->route('items.index')->with('success', 'Item created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Item $item)
    {
        $item->load([
            'group',
            'tags',
            'warehouseItems' => function ($query) {
                $query->whereIn('warehouse_id', function ($q) {
                    $q->select('id')->from('addrbooks')->where('type', \App\Models\Addrbook::TYPE_WAREHOUSE);
                })->with('warehouse');
            },
        ]);

        return Inertia::render('Items/Show', [
            'item' => $item,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Item $item)
    {
        $permissions = Item::getPermissions();
        if ($item->type === ItemType::ASSET_LANCAR) {
            Gate::authorize($permissions['asset_lancar_edit']);
        } else {
            Gate::authorize($permissions['edit']);
        }

        $item->load(['group', 'tags']);

        return Inertia::render('Items/Edit', [
            'item' => $item,
            'brands' => collect(ItemBrand::cases())->map(fn ($b) => ['value' => $b->value, 'label' => $b->label()]),
            'types' => collect(ItemType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'tags' => \App\Models\Tag::all()->groupBy('type'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Item $item)
    {
        $permissions = Item::getPermissions();
        if ($item->type === ItemType::ASSET_LANCAR) {
            Gate::authorize($permissions['asset_lancar_edit']);
        } else {
            Gate::authorize($permissions['edit']);
        }
        $request->validate([
            'pcode' => 'required',
        ]);

        try {
            $this->itemService->update(
                $item->id,
                (object) $request->except(['image', 'tags']),
                $request->input('tags', []),
                $request->file('image')
            );

            return redirect()->route('items.index')->with('success', 'Item updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Item $item)
    {
        $permissions = Item::getPermissions();
        if ($item->type === ItemType::ASSET_LANCAR) {
            Gate::authorize($permissions['asset_lancar_delete']);
        } else {
            Gate::authorize($permissions['delete']);
        }
        $item->delete();

        return redirect()->route('items.index')->with('success', 'Item deleted successfully.');
    }

    public function jubelio(Item $item, \App\Services\JubelioService $jubelioService)
    {
        Gate::authorize(Item::getPermissions()['view']);

        $item->load(['group', 'tags']);

        $message = 'ok';
        $dataJubelio = [];

        if ($item->jubelio_item_id > 0) {
            $token = $jubelioService->getCachedToken();

            if (! $token) {
                $message = 'Gagal otentikasi Jubelio';
            } else {
                try {
                    $response = \Illuminate\Support\Facades\Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'authorization' => $token,
                    ])->post('https://api2.jubelio.com/inventory/items/all-stocks/', [
                        'ids' => [$item->jubelio_item_id],
                    ]);

                    if ($response->successful()) {
                        $result = $response->json();
                        if (empty($result['data'])) {
                            $message = 'Item tidak ditemukan di Jubelio';
                        } else {
                            $dataJubelio = $result['data'][0];
                        }
                    } else {
                        $message = 'Gagal mengambil data dari Jubelio: '.$response->status();
                    }
                } catch (\Exception $e) {
                    $message = 'Error: '.$e->getMessage();
                }
            }
        } else {
            $message = 'Item belum terhubung dengan Jubelio';
        }

        return Inertia::render('Items/Jubelio', [
            'item' => $item,
            'dataJubelio' => $dataJubelio,
            'message' => $message,
        ]);
    }

    public function getJubelioItems(Item $item, \App\Services\JubelioService $jubelioService, Request $request)
    {
        Gate::authorize(Item::getPermissions()['edit']);

        $token = $jubelioService->getCachedToken();

        if (! $token) {
            return back()->withErrors(['message' => 'Gagal otentikasi Jubelio']);
        }

        $query = $request->input('q', $item->code);

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Content-Type' => 'application/json',
                'authorization' => $token,
            ])->get('https://api2.jubelio.com/inventory/items/to-stock/', [
                'q' => $query,
            ]);

            if ($response->successful()) {
                $result = $response->json();

                return Inertia::render('Items/JubelioSearch', [
                    'item' => $item,
                    'jubelioItems' => $result['data'] ?? [],
                    'query' => $query,
                ]);
            }

            return back()->withErrors(['message' => 'Gagal mengambil daftar item dari Jubelio']);
        } catch (\Exception $e) {
            return back()->withErrors(['message' => 'Error: '.$e->getMessage()]);
        }
    }

    public function updateJubelioId(Item $item, Request $request)
    {
        Gate::authorize(Item::getPermissions()['edit']);

        $request->validate([
            'jubelio_item_id' => 'nullable|integer',
        ]);

        $item->update([
            'jubelio_item_id' => $request->jubelio_item_id,
        ]);

        return redirect()->route('items.jubelio', $item->id)->with('success', 'Koneksi Jubelio diperbarui');
    }

    public function group(Request $request)
    {
        Gate::authorize(Item::getPermissions()['view']);

        $query = ItemGroup::query();

        if ($request->filled('kode')) {
            $query->where('name', 'like', "%{$request->kode}%");
        }

        if ($request->filled('alias')) {
            $query->where('alias', 'like', "%{$request->alias}%");
        }

        if ($request->filled('desc')) {
            $query->where('description', 'like', "%{$request->desc}%");
        }

        $groups = $query->orderBy('id', 'desc')->paginate(20)->withQueryString();

        return Inertia::render('Items/GroupIndex', [
            'groups' => $groups,
            'filters' => $request->only(['kode', 'alias', 'desc']),
        ]);
    }

    public function groupDetail(ItemGroup $group)
    {
        Gate::authorize(Item::getPermissions()['view']);

        $group->load([
            'items.warehouseItems' => function ($query) {
                $query->whereIn('warehouse_id', function ($q) {
                    $q->select('id')->from('addrbooks')->where('type', \App\Models\Addrbook::TYPE_WAREHOUSE);
                })->with('warehouse');
            },
        ]);

        return Inertia::render('Items/GroupShow', [
            'group' => $group,
        ]);
    }

    public function groupStats(Request $request, ItemGroup $group)
    {
        Gate::authorize(Item::getPermissions()['view']);

        $from = $request->input('from', now()->subMonths(11)->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());

        $data = \App\Models\StatSell::select([
            'type as transaction_type',
            \DB::raw("DATE_FORMAT(STR_TO_DATE(CONCAT(tahun, '-', bulan, '-01'), '%Y-%m-%d'), '%M %Y') as showdate"),
            'bulan',
            'tahun',
            \DB::raw('SUM(sum_qty) as total_qty'),
        ])
            ->where('group_id', $group->id)
            ->where(\DB::raw('tahun * 100 + bulan'), '>=', \DB::raw('YEAR("'.$from.'") * 100 + MONTH("'.$from.'")'))
            ->where(\DB::raw('tahun * 100 + bulan'), '<=', \DB::raw('YEAR("'.$to.'") * 100 + MONTH("'.$to.'")'))
            ->groupBy('showdate', 'type', 'bulan', 'tahun')
            ->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc')
            ->get();

        return Inertia::render('Items/GroupStats', [
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
            ->orderBy('date', 'desc')
            ->orderBy('transaction_id', 'desc')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Items/ItemTransactions', [
            'item' => $item->load('group'),
            'transactions' => $transactions,
        ]);
    }

    public function itemStats(Request $request, Item $item)
    {
        Gate::authorize(Item::getPermissions()['view']);

        $from = $request->input('from');
        $to = $request->input('to');
        $addrId = $request->input('addr');

        $query = \App\Models\TransactionDetail::select([
            'transaction_type',
            \DB::raw("DATE_FORMAT(date,'%M %Y') AS showdate"),
            \DB::raw("DATE_FORMAT(date,'%m') AS bulan"),
            \DB::raw("DATE_FORMAT(date,'%Y') AS tahun"),
            \DB::raw('SUM(quantity) as total_qty'),
        ])
            ->where('item_id', $item->id)
            ->whereIn('transaction_type', [
                \App\Models\Transaction::TYPE_SELL,
                \App\Models\Transaction::TYPE_MOVE,
                \App\Models\Transaction::TYPE_RETURN,
                \App\Models\Transaction::TYPE_PRODUCTION,
            ])
            ->groupBy('showdate', 'transaction_type', 'bulan', 'tahun')
            ->orderBy('tahun', 'DESC')
            ->orderBy('bulan', 'DESC');

        if ($from) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('date', '<=', $to);
        }

        if ($addrId) {
            $query->where(function ($q) use ($addrId) {
                $q->where('sender_id', $addrId)
                    ->orWhere('receiver_id', $addrId);
            });
        }

        $data = $query->get();

        $addrbooks = \App\Models\Addrbook::whereIn('type', [
            \App\Models\Addrbook::TYPE_CUSTOMER,
            \App\Models\Addrbook::TYPE_RESELLER,
            \App\Models\Addrbook::TYPE_WAREHOUSE,
        ])
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return Inertia::render('Items/ItemStats', [
            'item' => $item->load('group'),
            'data' => $data,
            'addrbooks' => $addrbooks,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'addr' => $addrId,
            ],
        ]);
    }
}
