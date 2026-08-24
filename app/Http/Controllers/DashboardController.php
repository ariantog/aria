<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboardService): View
    {
        $user = request()->user();

        return view('dashboard', [
            'dashboard' => $dashboardService->forUser($user),
        ]);
    }
}
