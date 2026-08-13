<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Support\LikeSearch;
use Illuminate\Http\Request;

class TransactionLookupController extends Controller
{
    /**
     * Search for sender or receiver based on transaction type.
     *
     * @param  string  $type  Transaction type (e.g., 'buy', 'sell')
     * @param  string  $role  'sender' or 'receiver'
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request, string $type, string $role)
    {
        abort_unless(Transaction::userCanAccessType($request->user(), $type), 403);
        // Remove dd and use request input
        // Configuration now gives us the Addrbook Type ID directly, or we get it from request
        $typeId = $request->input('addrbook_type') ?? ($request['addrbook_type'] ?? null);

        // We default to Addrbook model for all types now as per config
        $query = \App\Models\Addrbook::query()
            ->visibleToUser($request->user());

        // Apply type filtering
        if ($typeId !== null) {
            $typeIds = is_array($typeId) ? $typeId : explode(',', (string) $typeId);
            $query->whereIn('type', $typeIds);
        }

        // Apply search term
        if ($search = $request->input('search')) {
            $pattern = LikeSearch::contains($search);
            $query->where(function ($q) use ($pattern) {
                $q->where('customers.name', 'like', $pattern)
                    ->orWhere('customers.id', 'like', $pattern);
            });
        }

        $results = $query
            ->leftJoin('customerstat', 'customers.id', '=', 'customerstat.customer_id')
            ->select('customers.id', 'customers.name', 'customers.ppn', 'customers.type', 'customerstat.balance')
            ->orderBy('customers.name')
            ->limit(20)
            ->get();

        return response()->json($results);
    }
}
