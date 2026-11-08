<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\Mobile\MobileDashboardPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDashboardController extends Controller
{
    public function show(Request $request, DashboardService $dashboardService): JsonResponse
    {
        $user = $request->user();
        $dashboard = $dashboardService->forUser($user);

        return response()->json(MobileDashboardPresenter::fromDashboardData($dashboard));
    }
}
