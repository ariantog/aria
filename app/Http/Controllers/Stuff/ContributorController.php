<?php

namespace App\Http\Controllers\Stuff;

use App\Enums\ItemBrand;
use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContributorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->endOfMonth()->toDateString();
        $customerId = $request->customer_id;
        $filterBrand = $request->filterBrand;

        $baseQuery = TransactionDetail::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->where('transaction_details.transaction_type', Transaction::TYPE_SELL)
            ->whereBetween('transaction_details.date', [$from, $to]);

        if ($customerId) {
            $baseQuery->where('transaction_details.receiver_id', $customerId);
        }

        if ($filterBrand) {
            $baseQuery->whereHas('item', function ($q) use ($filterBrand) {
                $q->where('brand', $filterBrand);
            });
        }

        // Top 50 Items
        $topItems = (clone $baseQuery)
            ->join('items', 'items.id', '=', 'transaction_details.item_id')
            ->selectRaw('
                transaction_details.item_id, 
                items.name, 
                items.code, 
                items.brand, 
                (SELECT t.name FROM tags t WHERE t.id = items.genre LIMIT 1) as type_name,
                (SELECT t.name FROM tags t WHERE t.id = items.size LIMIT 1) as size_name,
                SUM(transaction_details.quantity) as qty, 
                SUM(transaction_details.total * (100 - transactions.discount_percent) / 100) as amount
            ')
            ->groupBy(
                'transaction_details.item_id',
                'items.id',
                'items.name',
                'items.code',
                'items.brand',
                'items.genre',
                'items.size'
            )
            ->orderByDesc('amount')
            ->limit(50)
            ->get()
            ->map(function ($row) {
                $brand = $row->brand instanceof ItemBrand ? $row->brand : ItemBrand::tryFrom($row->brand);

                return [
                    'item_id' => $row->item_id,
                    'item' => [
                        'name' => $row->name,
                        'code' => $row->code,
                    ],
                    'brand_label' => $brand ? $brand->label() : 'Unknown',
                    'type_label' => $row->type_name ?: 'acesoris',
                    'size_label' => $row->size_name ?: 'acesoris',
                    'qty' => (float) $row->qty,
                    'amount' => (float) $row->amount,
                ];
            });

        // Group by Brand
        $groupByBrand = (clone $baseQuery)
            ->join('items', 'items.id', '=', 'transaction_details.item_id')
            ->selectRaw('items.brand, SUM(transaction_details.quantity) as qty, SUM(transaction_details.total * (100 - transactions.discount_percent) / 100) as amount')
            ->groupBy('items.brand')
            ->orderByDesc('amount')
            ->get()
            ->map(function ($row) {
                $brand = $row->brand instanceof ItemBrand ? $row->brand : ItemBrand::tryFrom($row->brand);

                return [
                    'brand' => $row->brand,
                    'brand_label' => $brand ? $brand->label() : 'Unknown',
                    'qty' => (float) $row->qty,
                    'amount' => (float) $row->amount,
                ];
            });

        // Group by Type (Genre Tags)
        $groupByType = (clone $baseQuery)
            ->join('items', 'items.id', '=', 'transaction_details.item_id')
            ->leftJoin('tags', 'tags.id', '=', 'items.genre')
            ->selectRaw('COALESCE(tags.code, "acesoris") as type_label, SUM(transaction_details.quantity) as qty, SUM(transaction_details.total * (100 - transactions.discount_percent) / 100) as amount')
            ->groupBy('type_label')
            ->orderByDesc('amount')
            ->get()
            ->map(function ($row) {
                return [
                    'type_label' => $row->type_label,
                    'qty' => (float) $row->qty,
                    'amount' => (float) $row->amount,
                ];
            });

        // Group by Size
        $groupBySize = (clone $baseQuery)
            ->join('items', 'items.id', '=', 'transaction_details.item_id')
            ->leftJoin('tags', 'tags.id', '=', 'items.size')
            ->selectRaw('COALESCE(tags.name, "acesoris") as size, SUM(transaction_details.quantity) as qty, SUM(transaction_details.total * (100 - transactions.discount_percent) / 100) as amount')
            ->groupBy('size')
            ->orderByDesc('amount')
            ->get()
            ->map(function ($row) {
                return [
                    'size' => $row->size,
                    'qty' => (float) $row->qty,
                    'amount' => (float) $row->amount,
                ];
            });

        return Inertia::render('Stuff/Contributors/Index', [
            'stats' => [
                'topItems' => $topItems,
                'groupByBrand' => $groupByBrand,
                'groupByType' => $groupByType,
                'groupBySize' => $groupBySize,
            ],
            'filters' => [
                'from' => $from,
                'to' => $to,
                'customer_id' => $customerId,
                'filterBrand' => $filterBrand,
            ],
            'brandList' => array_map(fn ($b) => ['id' => $b->value, 'name' => $b->label()], ItemBrand::cases()),
            'customers' => Addrbook::warehouse()->get(['id', 'name']),
        ]);
    }
}
