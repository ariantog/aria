<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateScheduledTaskRequest;
use App\Models\ScheduledTask;
use App\Support\SchedulerHealth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

class ScheduledTaskController extends Controller
{
    public function index(): View
    {
        Gate::authorize(ScheduledTask::getPermissions()['view']);

        return view('system-settings.cron', [
            'tasks' => ScheduledTask::all(),
            'schedulerHealth' => SchedulerHealth::snapshot(),
            'can' => [
                'edit' => request()->user()?->can(ScheduledTask::getPermissions()['edit']) ?? false,
                'general_settings' => request()->user()?->can(\App\Models\Setting::getPermissions()['view']) ?? false,
            ],
        ]);
    }

    public function update(UpdateScheduledTaskRequest $request, ScheduledTask $scheduledTask): RedirectResponse
    {
        Gate::authorize(ScheduledTask::getPermissions()['edit']);

        $scheduledTask->update($request->validated());

        try {
            Artisan::call('schedule:clear-cache');
        } catch (\Throwable) {
            // Optional; ignore when schedule cache was never built.
        }

        return back()->with('success', 'Task updated successfully.');
    }

    public function toggle(ScheduledTask $scheduledTask): RedirectResponse
    {
        Gate::authorize(ScheduledTask::getPermissions()['edit']);

        $scheduledTask->update([
            'active' => ! $scheduledTask->active,
        ]);

        try {
            Artisan::call('schedule:clear-cache');
        } catch (\Throwable) {
            // Optional; ignore when schedule cache was never built.
        }

        return back()->with('success', 'Task status toggled.');
    }
}
