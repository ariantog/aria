<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
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
        \Illuminate\Support\Facades\Gate::authorize(\App\Models\Transaction::getPermissions()['view']);
        // Remove dd and use request input
        // Configuration now gives us the Addrbook Type ID directly, or we get it from request
        $typeId = $request->input('addrbook_type') ?? ($request['addrbook_type'] ?? null);

        // We default to Addrbook model for all types now as per config
        $query = \App\Models\Addrbook::query();

        // Apply type filtering
        if ($typeId !== null) {
            $typeIds = is_array($typeId) ? $typeId : explode(',', (string) $typeId);
            $query->whereIn('type', $typeIds);
        }

        // Apply search term
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('addrbooks.name', 'like', "%{$search}%")
                    ->orWhere('addrbooks.id', 'like', "%{$search}%");
            });
        }

        $results = $query
            ->leftJoin('addrbook_stats', 'addrbooks.id', '=', 'addrbook_stats.addrbook_id')
            ->select('addrbooks.id', 'addrbooks.name', 'addrbooks.ppn', 'addrbooks.type', 'addrbook_stats.balance')
            ->orderBy('addrbooks.name')
            ->limit(20)
            ->get();

        return response()->json($results);
    }
}
