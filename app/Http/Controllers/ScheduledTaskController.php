<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateScheduledTaskRequest;
use App\Models\ScheduledTask;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ScheduledTaskController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(Setting::getPermissions()['cron-view']);

        return Inertia::render('SystemSettings/Cron', [
            'tasks' => ScheduledTask::all(),
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
