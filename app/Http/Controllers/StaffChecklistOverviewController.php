<?php

namespace App\Http\Controllers;

use App\Models\ChecklistTemplate;
use App\Models\User;
use App\Services\StaffChecklistOverviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StaffChecklistOverviewController extends Controller
{
    public function index(Request $request, StaffChecklistOverviewService $overviewService)
    {
        Gate::authorize(User::getPermissions()['staff-roles-view']);

        $date = $request->query('date');

        return view('staff-checklists.index', [
            'overview' => $overviewService->build($date),
            'filters' => [
                'date' => $date,
            ],
            'canAssignStaffRoles' => $request->user()?->can(User::getPermissions()['staff-roles-edit']) ?? false,
            'canManageTemplates' => $request->user()?->can(User::getPermissions()['staff-roles-view']) ?? false,
            'canEditTemplates' => $request->user()?->can(ChecklistTemplate::getPermissions()['edit']) ?? false,
        ]);
    }
}
