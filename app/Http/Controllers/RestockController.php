<?php

namespace App\Http\Controllers;

use App\Imports\RestockImport;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\Restock;
use App\Models\RestockHistory;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class RestockController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(Restock::getPermissions()['view']);

        $sizeType = $request->get('size_type', 'alpha'); // alpha, volume, all
        $status = $request->get('status', 'restocked'); // restocked, production, shipped, missing
        $searchValue = $request->get('code');

        // Map status to column
        $qtyColumn = match ($status) {
            'production' => 'in_production_quantity',
            'shipped' => 'shipped_quantity',
            'missing' => 'missing_quantity',
            default => 'restocked_quantity'
        };

        // Map frontend tab values to database values
        $dbSizeType = match ($sizeType) {
            'alpha' => 'alpha-size',
            'volume' => 'volum-size',
            default => 'all'
        };

        // Define sizes based on tab for matrix headers
        $targetSizes = match ($sizeType) {
            'alpha' => ['S', 'M', 'L', 'XL'],
            'volume' => ['8OZ', '10OZ', '12OZ', '14OZ'],
            default => []
        };

        // Aggregated Query for Matrix View
        $query = Restock::query()
            ->leftJoin('items', 'restocks.item_id', '=', 'items.id')
            ->leftJoin('item_groups as ig_direct', 'restocks.group_id', '=', 'ig_direct.id')
            ->leftJoin('item_groups as ig_item', 'items.group_id', '=', 'ig_item.id')
            ->leftJoin('item_tag as it_color', function ($join) {
                $join->on('items.id', '=', 'it_color.item_id')
                    ->whereIn('it_color.tag_id', function ($q) {
                        $q->select('id')->from('tags')->where('type', \App\Models\Tag::TYPE_WARNA);
                    });
            })
            ->leftJoin('tags as t_color_direct', 'restocks.color_id', '=', 't_color_direct.id')
            ->leftJoin('tags as t_color_item', 'it_color.tag_id', '=', 't_color_item.id')
            ->leftJoin('item_tag as it_size', function ($join) {
                $join->on('items.id', '=', 'it_size.item_id')
                    ->whereIn('it_size.tag_id', function ($q) {
                        $q->select('id')->from('tags')->where('type', \App\Models\Tag::TYPE_SIZE);
                    });
            })
            ->leftJoin('tags as t_size_direct', function ($join) {
                $join->on('restocks.size_id', '=', 't_size_direct.id')
                    ->where('restocks.size_id', '!=', 'all');
            })
            ->leftJoin('tags as t_size_item', 'it_size.tag_id', '=', 't_size_item.id')
            ->select(
                DB::raw('COALESCE(restocks.group_id, items.group_id) as group_id'),
                DB::raw('COALESCE(ig_direct.name, ig_item.name, "No Group") as group_name'),
                DB::raw('COALESCE(items.pcode, ig_direct.master, ig_item.master) as pcode'),
                DB::raw('COALESCE(restocks.color_id, it_color.tag_id) as color_id'),
                DB::raw('COALESCE(t_color_direct.name, t_color_item.name) as color_name')
            );

        $groupBy = [
            DB::raw('COALESCE(restocks.group_id, items.group_id)'),
            DB::raw('COALESCE(ig_direct.name, ig_item.name, "No Group")'),
            DB::raw('COALESCE(items.pcode, ig_direct.master, ig_item.master)'),
            DB::raw('COALESCE(restocks.color_id, it_color.tag_id)'),
            DB::raw('COALESCE(t_color_direct.name, t_color_item.name)'),
        ];

        if ($sizeType === 'all') {
            $query->addSelect(DB::raw('COALESCE(t_size_direct.name, t_size_item.name, restocks.size_id) as size_name'));
            $groupBy[] = DB::raw('COALESCE(t_size_direct.name, t_size_item.name, restocks.size_id)');
        }

        $query->groupBy($groupBy);

        // Dynamic pivot columns for sizes
        foreach ($targetSizes as $size) {
            $safeSize = str_replace(['.', ' '], '_', strtolower($size));
            $query->addSelect(DB::raw("
                SUM(CASE 
                    WHEN t_size_direct.name = '{$size}' OR t_size_item.name = '{$size}' OR restocks.size_id = 'all' THEN restocks.{$qtyColumn} 
                    ELSE 0 
                END) as qty_{$safeSize}
            "));
        }

        $query->addSelect(DB::raw("SUM(restocks.{$qtyColumn}) as total_display_qty"));
        $query->addSelect(DB::raw('SUM(restocks.restocked_quantity) as total_restock'));
        $query->addSelect(DB::raw('SUM(restocks.in_production_quantity) as total_prod'));
        $query->addSelect(DB::raw('SUM(restocks.shipped_quantity) as total_ship'));
        $query->addSelect(DB::raw('SUM(restocks.missing_quantity) as total_missing'));

        // Filtering by Size Type column (New logic)
        $query->where(function ($q) use ($dbSizeType, $sizeType) {
            $q->where('restocks.size_type', $dbSizeType);

            // Fallback for legacy data (null size_type)
            if ($sizeType === 'alpha') {
                $q->orWhere(fn ($sq) => $sq->whereNull('restocks.size_type')
                    ->where(fn ($qq) => $qq->whereIn('t_size_direct.name', ['S', 'M', 'L', 'XL'])
                        ->orWhereIn('t_size_item.name', ['S', 'M', 'L', 'XL'])));
            } elseif ($sizeType === 'volume') {
                $q->orWhere(fn ($sq) => $sq->whereNull('restocks.size_type')
                    ->where(fn ($qq) => $qq->whereIn('t_size_direct.name', ['8OZ', '10OZ', '12OZ', '14OZ'])
                        ->orWhereIn('t_size_item.name', ['8OZ', '10OZ', '12OZ', '14OZ'])));
            } else {
                $q->orWhere(fn ($sq) => $sq->whereNull('restocks.size_type')
                    ->whereDoesntHave('item.tags', fn ($ssq) => $ssq->whereIn('tags.name', ['S', 'M', 'L', 'XL', '8OZ', '10OZ', '12OZ', '14OZ']))
                    ->where('restocks.size_id', '!=', 'all'));
            }
        });

        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('items.pcode', 'like', "%{$searchValue}%")
                    ->orWhere('item_groups.name', 'like', "%{$searchValue}%");
            });
        }

        $query->orderBy('group_name')->orderBy('pcode');

        $restocks = $query->paginate(50)->withQueryString();

        $cacheKey = 'gudang_cart_user_'.auth()->id();
        $cartCount = count(Cache::get($cacheKey, []));

        $restockCacheKey = 'cart_items_user_'.auth()->id();
        $restockCacheCount = count(Cache::get($restockCacheKey, []));

        return Inertia::render('Restock/Index', [
            'restocks' => $restocks,
            'cartCount' => $cartCount,
            'restockCacheCount' => $restockCacheCount,
            'filters' => $request->only(['size_type', 'status', 'code']),
            'targetSizes' => $targetSizes,
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        Gate::authorize(Restock::getPermissions()['edit']);

        $request->validate([
            'status' => 'required|string',
            'action' => 'required|string',
            'selection' => 'required|array',
            'date' => 'required|date',
        ]);

        $status = $request->status;
        $action = $request->action;
        $date = $request->date;
        $selection = $request->selection;

        DB::transaction(function () use ($status, $action, $date, $selection) {
            foreach ($selection as $row) {
                $groupId = $row['group_id'];
                $colorId = $row['color_id'];
                $pcode = $row['pcode'];
                $values = $row['values'];

                // Filter restocks strictly by group, color, and pcode
                $baseQuery = Restock::where('group_id', $groupId)
                    ->where('color_id', $colorId)
                    ->where(function ($q) use ($pcode) {
                        $q->whereHas('item', fn ($iq) => $iq->where('pcode', $pcode))
                            ->orWhereHas('group', fn ($gq) => $gq->where('master', $pcode));
                    });

                foreach ($values as $sizeName => $requestedQty) {
                    if ($requestedQty <= 0) {
                        continue;
                    }

                    // Resolve size ID
                    $sizeTagId = null;
                    if ($sizeName !== 'default' && $sizeName !== 'all' && $sizeName !== 'All Sizes') {
                        $sizeTag = \App\Models\Tag::where('name', $sizeName)->where('type', \App\Models\Tag::TYPE_SIZE)->first();
                        $sizeTagId = $sizeTag?->id;
                    }

                    // Find Restock records matching the specific size OR 'all' pool
                    $query = (clone $baseQuery)->where(function ($q) use ($sizeTagId) {
                        if ($sizeTagId === null) {
                            $q->where('size_id', 'all')->orWhereNull('size_id');
                        } else {
                            $q->where('size_id', $sizeTagId)
                                ->orWhere('size_id', 'all')
                                ->orWhereNull('size_id');
                        }
                    });

                    $restocks = $query->lockForUpdate()->get();

                    if ($action === 'add_stock') {
                        $restock = $restocks->first();
                        if ($restock) {
                            $before = $restock->restocked_quantity;
                            $restock->update([
                                'restocked_quantity' => $requestedQty,
                                'date' => $date,
                            ]);
                            $restockId = $restock->id;
                            $actualSizeId = $restock->size_id;
                            $after = $requestedQty;
                            $qtyChanged = $requestedQty - $before;
                        } else {
                            // This part is a bit tricky since we don't have item_id easily here if not in $restocks
                            // But usually, since it's on the index, it should exist.
                            // Fallback to searching item first to get correct IDs
                            $item = Item::where('group_id', $groupId)
                                ->whereHas('tags', fn ($q) => $q->where('id', $colorId))
                                ->where(function ($q) use ($sizeTagId) {
                                    if ($sizeTagId && $sizeTagId !== 'all') {
                                        $q->whereHas('tags', fn ($sq) => $sq->where('id', $sizeTagId));
                                    }
                                })
                                ->where('pcode', $pcode)
                                ->first();

                            $restock = Restock::create([
                                'item_id' => $item?->id,
                                'group_id' => $groupId,
                                'color_id' => $colorId,
                                'size_id' => $sizeTagId ?: 'all',
                                'size_type' => $this->determineSizeType($sizeName === 'default' ? '' : $sizeName),
                                'date' => $date,
                                'status' => 1,
                                'restocked_quantity' => $requestedQty,
                            ]);
                            $before = 0;
                            $restockId = $restock->id;
                            $actualSizeId = $restock->size_id;
                            $after = $requestedQty;
                            $qtyChanged = $requestedQty;
                        }

                        RestockHistory::create([
                            'restock_id' => $restockId,
                            'group_id' => $groupId,
                            'color_id' => $colorId,
                            'size_id' => $actualSizeId,
                            'step' => 'restocked',
                            'action' => 'edited',
                            'qty_before' => $before,
                            'qty_after' => $after,
                            'qty_changed' => $qtyChanged,
                            'user_id' => auth()->id(),
                            'date' => $date,
                        ]);

                        continue;
                    }

                    foreach ($restocks as $restock) {
                        $available = $this->getAvailableQty($restock, $status);
                        $move = min($requestedQty, $available);
                        if ($move <= 0) {
                            continue;
                        }

                        if ($action === 'to_arrived') {
                            $this->handleBulkArrived($restock, $move);
                        } else {
                            $this->applyMovement($restock, $status, $action, $move, $date);
                        }

                        $requestedQty -= $move;
                        if ($requestedQty <= 0) {
                            break;
                        }
                    }
                }
            }
        });

        return back()->with('success', 'Bulk action completed.');
    }

    private function handleBulkArrived($restock, $qty)
    {
        // Must have item_id to add to gudang cart
        if (! $restock->item_id) {
            // Attempt to find matching item
            $item = Item::where('group_id', $restock->group_id)
                ->whereHas('tags', fn ($q) => $q->where('id', $restock->color_id))
                ->whereHas('tags', fn ($q) => $q->where('id', $restock->size_id))
                ->first();

            if ($item) {
                $restock->item_id = $item->id;
                $restock->save();
            } else {
                return; // Can't add to cart without item
            }
        }

        $cacheKey = 'gudang_cart_user_'.auth()->id();
        $cart = Cache::get($cacheKey, []);

        $found = false;
        foreach ($cart as &$row) {
            if ($row['itemId'] == $restock->item_id) {
                $row['quantity'] += $qty;
                $row['subtotal'] = $row['quantity'] * $row['price'];
                $found = true;
                break;
            }
        }

        if (! $found) {
            $item = $restock->item;
            $cart[] = [
                'itemId' => $restock->item_id,
                'code' => $item->code ?: $item->id,
                'name' => $item->name,
                'quantity' => (int) $qty,
                'price' => $item->price ?? 0,
                'subtotal' => (int) $qty * ($item->price ?? 0),
            ];
        }

        Cache::put($cacheKey, $cart, now()->addHour());
    }

    private function getAvailableQty($restock, $status)
    {
        return match ($status) {
            'restocked' => $restock->restocked_quantity,
            'production' => $restock->in_production_quantity,
            'shipped' => $restock->shipped_quantity,
            'missing' => $restock->missing_quantity,
            default => 0
        };
    }

    private function applyMovement($restock, $status, $action, $qty, $date)
    {
        $beforeValue = 0;
        $afterValue = 0;
        $step = '';

        if ($action === 'to_production' && $status === 'restocked') {
            $beforeValue = $restock->in_production_quantity;
            $restock->restocked_quantity -= $qty;
            $restock->in_production_quantity += $qty;
            $afterValue = $restock->in_production_quantity;
            $step = 'production';
        } elseif ($action === 'to_shipped' && $status === 'production') {
            $beforeValue = $restock->shipped_quantity;
            $restock->in_production_quantity -= $qty;
            $restock->shipped_quantity += $qty;
            $afterValue = $restock->shipped_quantity;
            $step = 'shipped';
        } elseif ($action === 'to_missing') {
            $beforeValue = $restock->missing_quantity;
            if ($status === 'restocked') {
                $restock->restocked_quantity -= $qty;
            } elseif ($status === 'production') {
                $restock->in_production_quantity -= $qty;
            } elseif ($status === 'shipped') {
                $restock->shipped_quantity -= $qty;
            }

            $restock->missing_quantity += $qty;
            $afterValue = $restock->missing_quantity;
            $step = 'missing';
        }

        $restock->date = $date;
        $restock->save();

        RestockHistory::create([
            'restock_id' => $restock->id,
            'item_id' => $restock->item_id,
            'group_id' => $restock->group_id,
            'color_id' => $restock->color_id,
            'size_id' => $restock->size_id,
            'step' => $step,
            'action' => 'edited',
            'qty_before' => $beforeValue,
            'qty_after' => $afterValue,
            'qty_changed' => $qty,
            'user_id' => auth()->id(),
            'date' => $date,
        ]);
    }

    public function create()
    {
        Gate::authorize(Restock::getPermissions()['create']);

        $userId = auth()->id();
        $cacheKey = "cart_items_user_{$userId}";
        $items = Cache::get($cacheKey, []);

        return Inertia::render('Restock/Create', [
            'items' => $items,
        ]);
    }

    public function addItem(Request $request)
    {
        Gate::authorize(Restock::getPermissions()['create']);

        $userId = auth()->id();
        $cacheKey = "cart_items_user_{$userId}";
        $items = Cache::get($cacheKey, []);

        // Option 1: Add by Attributes (Group, Color, Size)
        if ($request->filled('group_id') && $request->filled('color_id') && $request->filled('size_id')) {
            $group = ItemGroup::findOrFail($request->group_id);
            $color = \App\Models\Tag::findOrFail($request->color_id);

            $sizeName = '';
            $sizeType = $request->size_type ?? 'all';

            if ($request->size_id === 'all') {
                $sizeName = 'All Sizes';
            } else {
                $sizeTag = \App\Models\Tag::findOrFail($request->size_id);
                $sizeName = $sizeTag->name;
                // Auto-detect size type if not explicitly provided
                if (! $request->has('size_type')) {
                    $sizeType = $this->determineSizeType($sizeTag->name);
                }
            }

            $uniqueKey = "attr_{$request->group_id}_{$request->color_id}_{$request->size_id}_{$sizeType}";

            $found = false;
            foreach ($items as &$item) {
                if (isset($item['unique_key']) && $item['unique_key'] === $uniqueKey) {
                    $item['qty'] += $request->qty;
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $items[] = [
                    'unique_key' => $uniqueKey,
                    'group_id' => $request->group_id,
                    'group_name' => $group->name,
                    'color_id' => $request->color_id,
                    'color_name' => $color->name,
                    'size_id' => $request->size_id,
                    'size_name' => $sizeName,
                    'size_type' => $sizeType,
                    'qty' => $request->qty,
                ];
            }

            Cache::put($cacheKey, $items, now()->addHour());

            return back()->with('success', 'Attributes added to restock list.');
        }

        // Option 2: Add by Code (Existing logic)
        $request->validate([
            'code' => 'required',
            'qty' => 'required|integer|min:1',
        ]);

        // 1. Try to find ItemGroup first
        $group = ItemGroup::where('id', $request->code)
            ->orWhere('master', $request->code)
            ->orWhere('name', $request->code)
            ->first();

        if ($group) {
            // Check if group already in cache
            foreach ($items as $item) {
                if (isset($item['group_id']) && $item['group_id'] == $group->id) {
                    return back()->with('error', "Group {$group->name} sudah ada di daftar restock (cache).");
                }
            }

            // Check if group already in database restock registry
            $existsInDb = Restock::where('group_id', $group->id)->exists();
            if ($existsInDb) {
                return back()->with('error', "Group {$group->name} sudah terdaftar di database restock.");
            }

            $groupItems = $group->items()
                ->whereIn('type', [\App\Enums\ItemType::ITEM, \App\Enums\ItemType::ASSET_LANCAR])
                ->get();
            $count = 0;
            foreach ($groupItems as $itemData) {
                $this->addItemToCart($items, $itemData, $request->qty);
                $count++;
            }

            if ($count > 0) {
                Cache::put($cacheKey, $items, now()->addHour());

                return back()->with('success', "{$count} items from group {$group->name} added to restock list.");
            }
        }

        // 2. Fallback to single Item search
        $itemData = Item::where(function ($q) use ($request) {
            $q->where('id', $request->code)
                ->orWhere('code', $request->code);
        })
            ->whereIn('type', [\App\Enums\ItemType::ITEM, \App\Enums\ItemType::ASSET_LANCAR])
            ->first();

        if ($itemData) {
            // Check if item already in cache
            foreach ($items as $item) {
                if (isset($item['id']) && $item['id'] == $itemData->id) {
                    return back()->with('error', "Item {$itemData->name} sudah ada di daftar restock (cache).");
                }
            }

            // Check if item already in database restock registry
            $existsInDb = Restock::where('item_id', $itemData->id)->exists();
            if ($existsInDb) {
                return back()->with('error', "Item {$itemData->name} sudah terdaftar di database restock.");
            }

            $this->addItemToCart($items, $itemData, $request->qty);
            Cache::put($cacheKey, $items, now()->addHour());

            return back()->with('success', 'Item added to restock list.');
        }

        return back()->with('error', 'Item or Group not found');
    }

    private function determineSizeType($sizeName)
    {
        $sizeName = strtoupper($sizeName);
        if (in_array($sizeName, ['S', 'M', 'L', 'XL'])) {
            return 'alpha-size';
        }
        if (in_array($sizeName, ['8OZ', '10OZ', '12OZ', '14OZ'])) {
            return 'volum-size';
        }

        return 'all';
    }

    private function addItemToCart(&$items, $itemData, $qty)
    {
        $found = false;
        foreach ($items as &$item) {
            if (isset($item['id']) && $item['id'] == $itemData->id) {
                $item['qty'] += $qty;
                $found = true;
                break;
            }
        }

        if (! $found) {
            // Attempt to find color/size tags for display
            $colorTag = $itemData->tags()->where('type', \App\Models\Tag::TYPE_WARNA)->first();
            $sizeTag = $itemData->tags()->where('type', \App\Models\Tag::TYPE_SIZE)->first();

            $sizeType = $sizeTag ? $this->determineSizeType($sizeTag->name) : 'all';

            $items[] = [
                'unique_key' => "item_{$itemData->id}",
                'code' => $itemData->code ?: (string) $itemData->id,
                'name' => $itemData->name,
                'qty' => $qty,
                'id' => $itemData->id,
                'group_id' => $itemData->group_id,
                'color_id' => $colorTag?->id,
                'color_name' => $colorTag?->name,
                'size_id' => $sizeTag?->id,
                'size_name' => $sizeTag?->name,
                'size_type' => $sizeType,
            ];
        }
    }

    public function removeItem($uniqueKey)
    {
        Gate::authorize(Restock::getPermissions()['create']);

        $userId = auth()->id();
        $cacheKey = "cart_items_user_{$userId}";
        $items = Cache::get($cacheKey, []);

        $items = array_values(array_filter($items, function ($item) use ($uniqueKey) {
            return ($item['unique_key'] ?? $item['code']) != $uniqueKey;
        }));

        Cache::put($cacheKey, $items, now()->addHour());

        return redirect()->route('restock.create')->with('success', 'Item removed from restock list.');
    }

    public function updateItemQty(Request $request, $uniqueKey)
    {
        Gate::authorize(Restock::getPermissions()['create']);

        $request->validate([
            'qty' => 'required|integer|min:1',
        ]);

        $userId = auth()->id();
        $cacheKey = "cart_items_user_{$userId}";
        $items = Cache::get($cacheKey, []);

        foreach ($items as &$item) {
            if (($item['unique_key'] ?? $item['code']) == $uniqueKey) {
                $item['qty'] = (int) $request->qty;
                break;
            }
        }

        Cache::put($cacheKey, $items, now()->addHour());

        return back()->with('success', 'Quantity updated.');
    }

    public function clearItems()
    {
        Gate::authorize(Restock::getPermissions()['create']);

        $userId = auth()->id();
        $cacheKey = "cart_items_user_{$userId}";
        Cache::forget($cacheKey);

        return redirect()->route('restock.create')->with('success', 'Restock list cleared.');
    }

    public function store(Request $request)
    {
        Gate::authorize(Restock::getPermissions()['create']);

        $request->validate([
            'date' => 'required|date',
        ]);

        $userId = auth()->id();
        $cacheKey = "cart_items_user_{$userId}";
        $items = Cache::get($cacheKey, []);

        if (empty($items)) {
            return back()->withErrors(['item' => 'Tidak ada data untuk disimpan']);
        }

        DB::transaction(function () use ($items, $request, $cacheKey) {
            foreach ($items as $item) {
                // Determine search criteria
                $criteria = [];
                if (isset($item['id'])) {
                    $criteria['item_id'] = $item['id'];
                } else {
                    $criteria['group_id'] = $item['group_id'];
                    $criteria['color_id'] = $item['color_id'];
                    $criteria['size_id'] = $item['size_id'];
                }

                $criteria['size_type'] = $item['size_type'] ?? 'all';

                $restock = Restock::where($criteria)->lockForUpdate()->first();

                if ($restock) {
                    $before = $restock->restocked_quantity;
                    $restock->increment('restocked_quantity', $item['qty']);

                    $updateData = [
                        'date' => $request->date,
                    ];

                    if (isset($item['id'])) {
                        if (empty($restock->group_id) && ! empty($item['group_id'])) {
                            $updateData['group_id'] = $item['group_id'];
                        }
                        if (empty($restock->color_id) && ! empty($item['color_id'])) {
                            $updateData['color_id'] = $item['color_id'];
                        }
                        if (empty($restock->size_id) && ! empty($item['size_id'])) {
                            $updateData['size_id'] = $item['size_id'];
                        }
                    }

                    $restock->update($updateData);
                    $after = $before + $item['qty'];
                } else {
                    $createData = array_merge($criteria, [
                        'date' => $request->date,
                        'status' => 1,
                        'restocked_quantity' => $item['qty'],
                    ]);

                    if (isset($item['id'])) {
                        $createData['group_id'] = $item['group_id'] ?? null;
                        $createData['color_id'] = $item['color_id'] ?? null;
                        $createData['size_id'] = $item['size_id'] ?? null;
                    }

                    $restock = Restock::create($createData);
                    $before = 0;
                    $after = $item['qty'];
                }

                RestockHistory::create([
                    'restock_id' => $restock->id,
                    'item_id' => $item['id'] ?? null,
                    'group_id' => $item['group_id'] ?? null,
                    'color_id' => $item['color_id'] ?? null,
                    'size_id' => $item['size_id'] ?? null,
                    'size_type' => $item['size_type'] ?? 'all',
                    'step' => 'restocked',
                    'action' => 'created',
                    'qty_before' => $before,
                    'qty_after' => $after,
                    'qty_changed' => $item['qty'],
                    'user_id' => auth()->id(),
                    'date' => $request->date,
                ]);
            }

            Cache::forget($cacheKey);
        });

        return redirect()->route('restock.index')->with('success', 'Data restock berhasil disimpan');
    }

    public function update($id)
    {
        Gate::authorize(Restock::getPermissions()['edit']);

        $restock = Restock::with('item')->findOrFail($id);

        return Inertia::render('Restock/Update', [
            'restock' => $restock,
        ]);
    }

    public function updateQty(Request $request, $id)
    {
        Gate::authorize(Restock::getPermissions()['edit']);

        $request->validate([
            'type' => 'required|in:restocked,production,shipped,missing',
            'qty' => 'required|integer|min:1',
            'invoice' => 'nullable|string',
            'date' => 'required|date',
        ]);

        DB::transaction(function () use ($request, $id) {
            $restock = Restock::lockForUpdate()->findOrFail($id);
            $qty = (int) $request->qty;
            $type = $request->type;

            $beforeValue = 0;
            $afterValue = 0;

            switch ($type) {
                case 'restocked':
                    $beforeValue = $restock->restocked_quantity;
                    $restock->restocked_quantity += $qty;
                    $afterValue = $restock->restocked_quantity;
                    break;
                case 'production':
                    if ($restock->restocked_quantity < $qty) {
                        throw new \Exception('Restocked quantity tidak cukup');
                    }
                    $beforeValue = $restock->in_production_quantity;
                    $restock->restocked_quantity -= $qty;
                    $restock->in_production_quantity += $qty;
                    $afterValue = $restock->in_production_quantity;
                    break;
                case 'shipped':
                    if ($restock->in_production_quantity < $qty) {
                        throw new \Exception('Production quantity tidak cukup');
                    }
                    $beforeValue = $restock->shipped_quantity;
                    $restock->in_production_quantity -= $qty;
                    $restock->shipped_quantity += $qty;
                    $afterValue = $restock->shipped_quantity;
                    break;
                case 'missing':
                    $beforeValue = $restock->missing_quantity;
                    $restock->missing_quantity += $qty;
                    $afterValue = $restock->missing_quantity;
                    break;
            }

            $restock->date = $request->date;
            $restock->save();

            RestockHistory::create([
                'restock_id' => $restock->id,
                'item_id' => $restock->item_id,
                'step' => $type,
                'action' => 'edited',
                'qty_before' => $beforeValue,
                'qty_after' => $afterValue,
                'qty_changed' => $qty,
                'invoice' => $request->invoice,
                'user_id' => auth()->id(),
                'date' => $request->date,
            ]);
        });

        return redirect()->route('restock.index')->with('success', 'Stock updated successfully');
    }

    public function received()
    {
        Gate::authorize(Restock::getPermissions()['view']);

        $cacheKey = 'gudang_cart_user_'.auth()->id();
        $items = Cache::get($cacheKey, []);

        return Inertia::render('Restock/Received', [
            'items' => $items,
        ]);
    }

    public function removeCartItem($code)
    {
        Gate::authorize(Restock::getPermissions()['edit']);

        $userId = auth()->id();
        $cacheKey = "gudang_cart_user_{$userId}";
        $items = Cache::get($cacheKey, []);

        $items = array_values(array_filter($items, function ($item) use ($code) {
            return $item['code'] != $code;
        }));

        Cache::put($cacheKey, $items, now()->addHour());

        return redirect()->route('restock.received')->with('success', 'Item removed from restock list.');
    }

    public function receiveStore(Request $request, TransactionService $transactionService)
    {
        Gate::authorize(Restock::getPermissions()['edit']);

        $request->validate([
            'date' => 'required|date',
            'invoice' => 'nullable|string',
        ]);

        $cacheKey = 'gudang_cart_user_'.auth()->id();
        $items = Cache::get($cacheKey, []);

        if (empty($items)) {
            return back()->withErrors(['gudang' => 'Cart kosong']);
        }

        $errors = [];
        foreach ($items as $row) {
            $restock = Restock::where('item_id', $row['itemId'])->first();
            if (! $restock || $row['quantity'] > $restock->shipped_quantity) {
                $errors[] = "Item {$row['name']} tidak cukup shipped qty";
            }
        }

        if (! empty($errors)) {
            return back()->withErrors(['gudang' => implode(', ', $errors)]);
        }

        $transactionId = DB::transaction(function () use ($items, $request, $transactionService) {
            $ids = collect($items)->pluck('itemId')->toArray();
            $restocks = Restock::whereIn('item_id', $ids)->lockForUpdate()->get()->keyBy('item_id');

            // Create Transaction
            $transaction = Transaction::create([
                'date' => $request->date,
                'type' => Transaction::TYPE_BUY,
                'sender_id' => 373, // Supplier Umum
                'receiver_id' => 2875, // Gudang - Online Sambisari
                'invoice' => $request->invoice,
                'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
                'user_id' => auth()->id(),
                'status' => Transaction::STATUS_COMPLETED,
            ]);

            foreach ($items as $row) {
                $restock = $restocks[$row['itemId']];
                $before = $restock->shipped_quantity;
                $after = $before - $row['quantity'];

                $restock->decrement('shipped_quantity', $row['quantity']);

                RestockHistory::create([
                    'restock_id' => $restock->id,
                    'item_id' => $restock->item_id,
                    'step' => 'received',
                    'action' => 'edited',
                    'qty_before' => $before,
                    'qty_after' => $after,
                    'qty_changed' => $row['quantity'],
                    'invoice' => $request->invoice,
                    'user_id' => auth()->id(),
                    'date' => $request->date,
                ]);

                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'item_id' => $row['itemId'],
                    'quantity' => $row['quantity'],
                    'price' => $row['price'],
                    'total' => $row['subtotal'],
                    'date' => $request->date,
                    'sender_id' => 373,
                    'receiver_id' => 2875,
                ]);
            }

            $transaction->total = collect($items)->sum('subtotal');
            $transaction->grand_total = $transaction->total;
            $transaction->save();

            $transactionService->handleTransaction($transaction);

            return $transaction->id;
        });

        Cache::forget($cacheKey);

        return redirect()->route('transactions.show', $transactionId)->with('success', 'Transaction created.');
    }

    public function addToGudangCart($id, Request $request)
    {
        Gate::authorize(Restock::getPermissions()['edit']);

        $restock = Restock::with('item')->findOrFail($id);

        $request->validate([
            'quantity' => "required|integer|min:1|max:{$restock->shipped_quantity}",
        ]);

        $cacheKey = 'gudang_cart_user_'.auth()->id();
        $cart = Cache::get($cacheKey, []);

        foreach ($cart as $row) {
            if ($row['itemId'] == $restock->item_id) {
                return back()->with('error', 'Item sudah ada di cart gudang');
            }
        }

        $cart[] = [
            'itemId' => $restock->item_id,
            'code' => $restock->item->code ?: $restock->item->id,
            'name' => $restock->item->name,
            'quantity' => (int) $request->quantity,
            'price' => $restock->item->price ?? 0,
            'subtotal' => (int) $request->quantity * ($restock->item->price ?? 0),
        ];

        Cache::put($cacheKey, $cart, now()->addHour());

        return back()->with('success', 'Item masuk ke Gudang Cart');
    }

    public function history($restockId)
    {
        Gate::authorize(Restock::getPermissions()['history']);

        $restock = Restock::with('item')->findOrFail($restockId);
        $histories = RestockHistory::with('user')
            ->where('restock_id', $restockId)
            ->orderBy('id', 'desc')
            ->paginate(50);

        return Inertia::render('Restock/History', [
            'restock' => $restock,
            'histories' => $histories,
        ]);
    }

    public function resetSingleQty($id, Request $request)
    {
        Gate::authorize(Restock::getPermissions()['edit']);

        $request->validate([
            'type' => 'required|in:restocked,production,shipped',
        ]);

        DB::transaction(function () use ($request, $id) {
            $restock = Restock::lockForUpdate()->findOrFail($id);
            $type = $request->type;
            $before = 0;

            if ($type === 'restocked') {
                $before = $restock->restocked_quantity;
                $restock->restocked_quantity = 0;
            } elseif ($type === 'production') {
                $before = $restock->in_production_quantity;
                $restock->in_production_quantity = 0;
            } elseif ($type === 'shipped') {
                $before = $restock->shipped_quantity;
                $restock->shipped_quantity = 0;
            }

            $restock->save();

            RestockHistory::create([
                'restock_id' => $restock->id,
                'item_id' => $restock->item_id,
                'step' => $type,
                'action' => 'reset',
                'qty_before' => $before,
                'qty_after' => 0,
                'qty_changed' => $before,
                'user_id' => auth()->id(),
                'date' => now(),
            ]);
        });

        return redirect()->route('restock.index')->with('success', ucfirst($request->type).' qty reset ke 0');
    }

    public function uploadExcel()
    {
        Gate::authorize(Restock::getPermissions()['view']);

        return Inertia::render('Restock/UploadExcel');
    }

    public function importExcel(Request $request)
    {
        Gate::authorize(Restock::getPermissions()['create']);

        $request->validate([
            'file' => 'required|mimes:xlsx,csv',
            'date' => 'required|date',
            'type' => 'required|in:restocked,production,shipped,missing',
        ]);

        $import = new RestockImport($request->date, $request->type);
        Excel::import($import, $request->file('file'));

        if (! empty($import->errors)) {
            return back()->withErrors(['import' => implode(', ', $import->errors)]);
        }

        return redirect()->route('restock.index')->with('success', 'Import Restock Berhasil');
    }
}
