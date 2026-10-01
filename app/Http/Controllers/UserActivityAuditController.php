<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Services\UserActivityAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserActivityAuditController extends Controller
{
    public function index(Request $request, UserActivityAuditService $audit): View
    {
        Gate::authorize(Setting::getPermissions()['view']);

        $filters = $audit->resolveFilters($request->query());
        $suspicious = $audit->paginateSuspiciousTiming($filters);
        $virtualMoves = $audit->frequentVirtualWarehouseMoves($filters);
        $discountedSells = $audit->frequentDiscountedOrZeroSells($filters);

        $filterUser = $filters['user_id']
            ? User::query()->find($filters['user_id'])
            : null;

        return view('system-settings.user-activity-audit', [
            'filters' => $filters,
            'filterUser' => $filterUser,
            'suspicious' => $suspicious,
            'virtualMoves' => $virtualMoves,
            'discountedSells' => $discountedSells,
            'audit' => $audit,
        ]);
    }
}
