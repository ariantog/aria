<?php

namespace App\Http\Controllers;

use App\Models\Report;
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
        $user = $request->user();
        abort_unless(
            Transaction::userCanAccessType($user, $type)
            || $user?->can(Report::getPermissions()['view-export-sell']),
            403
        );
        // Remove dd and use request input
        // We default to Addrbook model for all types now as per config
        $query = \App\Models\Addrbook::query()
            ->visibleToUser($request->user());

        // Apply type filtering
        if ($type === 'move') {
            $allowed = Transaction::movePartyAddrbookTypeIds($user);
            $typeId = $request->input('addrbook_type') ?? ($request['addrbook_type'] ?? null);
            if ($typeId !== null) {
                $requested = is_array($typeId) ? $typeId : explode(',', (string) $typeId);
                $requested = array_map(intval(...), $requested);
                $typeIds = array_values(array_intersect($requested, $allowed));
                $typeIds = $typeIds !== [] ? $typeIds : $allowed;
            } else {
                $typeIds = $allowed;
            }
            $query->whereIn('type', $typeIds);
        } elseif (($typeId = $request->input('addrbook_type') ?? ($request['addrbook_type'] ?? null)) !== null) {
            $typeIds = is_array($typeId) ? $typeId : explode(',', (string) $typeId);
            $query->whereIn('type', $typeIds);
        }

        // Apply search term (require 3+ characters; empty/short queries return nothing)
        $search = trim((string) $request->input('search', ''));
        if (strlen($search) <= 2) {
            return response()->json([]);
        }

        $pattern = LikeSearch::contains($search);
        $query->where(function ($q) use ($pattern) {
            $q->where('customers.name', 'like', $pattern)
                ->orWhere('customers.id', 'like', $pattern)
                ->orWhere('customers.description', 'like', $pattern)
                ->orWhere('customers.ledger_hint', 'like', $pattern);
        });

        $results = $query
            ->leftJoin('customerstat', 'customers.id', '=', 'customerstat.customer_id')
            ->select(
                'customers.id',
                'customers.name',
                'customers.ppn',
                'customers.ppn_included',
                'customers.type',
                'customers.description',
                'customers.ledger_hint',
                'customerstat.balance'
            )
            ->orderBy('customers.name')
            ->limit(8)
            ->get();

        return response()->json($results);
    }
}
