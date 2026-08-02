<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateScheduledTaskRequest;
use App\Models\ScheduledTask;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ScheduledTaskController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Setting::getPermissions()['cron-view']);

        return view('system-settings.cron', [
            'tasks' => ScheduledTask::all(),
            'can' => [
                'edit' => request()->user()?->can(Setting::getPermissions()['cron-edit']) ?? false,
            ],
        ]);
    }

    public function update(UpdateScheduledTaskRequest $request, ScheduledTask $scheduledTask): RedirectResponse
    {
        Gate::authorize(Setting::getPermissions()['cron-edit']);

        $scheduledTask->update($request->validated());

        return back()->with('success', 'Task updated successfully.');
    }

    public function toggle(ScheduledTask $scheduledTask): RedirectResponse
    {
        Gate::authorize(Setting::getPermissions()['cron-edit']);

        $scheduledTask->update([
            'is_active' => ! $scheduledTask->is_active,
        ]);

        return back()->with('success', 'Task status toggled.');
    }
}
