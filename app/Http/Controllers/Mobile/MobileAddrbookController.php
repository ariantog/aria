<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Transaction;
use App\Support\LikeSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAddrbookController extends Controller
{
    /**
     * Party lookup for mobile (same rules as web transaction lookup, shorter minimum query).
     */
    public function search(Request $request, string $type): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            Transaction::userCanAccessType($user, $type)
            || $user?->can(Report::getPermissions()['view-export-sell']),
            403
        );

        $query = \App\Models\Addrbook::query()->visibleToUser($user);

        if ($type === 'move') {
            $allowed = Transaction::movePartyAddrbookTypeIds($user);
            $typeId = $request->input('addrbook_type');
            if ($typeId !== null) {
                $requested = is_array($typeId) ? $typeId : explode(',', (string) $typeId);
                $requested = array_map(intval(...), $requested);
                $typeIds = array_values(array_intersect($requested, $allowed));
                $typeIds = $typeIds !== [] ? $typeIds : $allowed;
            } else {
                $typeIds = $allowed;
            }
            $query->whereIn('type', $typeIds);
        } elseif (($typeId = $request->input('addrbook_type')) !== null) {
            $typeIds = is_array($typeId) ? $typeId : explode(',', (string) $typeId);
            $query->whereIn('type', $typeIds);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search === '' || strlen($search) <= 2) {
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
            ->limit(20)
            ->get();

        return response()->json($results);
    }
}
