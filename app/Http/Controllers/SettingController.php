<?php

namespace App\Http\Controllers;

use App\Models\ScheduledTask;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;

class SettingController extends Controller
{
    public function index()
    {
        Gate::authorize(Setting::getPermissions()['view']);

        return view('system-settings.index', [
            'settings' => Setting::orderBy('group')->orderBy('name')->get(),
            'groups' => Setting::select('group')->distinct()->orderBy('group')->pluck('group'),
            'can' => [
                'create' => request()->user()?->can(Setting::getPermissions()['create']) ?? false,
                'edit' => request()->user()?->can(Setting::getPermissions()['edit']) ?? false,
                'delete' => request()->user()?->can(Setting::getPermissions()['delete']) ?? false,
                'cron_view' => request()->user()?->can(ScheduledTask::getPermissions()['view']) ?? false,
            ],
        ]);
    }

    public function create()
    {
        Gate::authorize(Setting::getPermissions()['create']);

        return view('system-settings.create');
    }

    public function store(\App\Http\Requests\StoreSettingRequest $request)
    {
        Gate::authorize(Setting::getPermissions()['create']);

        \App\Models\Setting::create($request->validated());

        return redirect()->route('system-settings.index')->with('success', 'Setting created successfully.');
    }

    public function edit(\App\Models\Setting $system_setting)
    {
        Gate::authorize(Setting::getPermissions()['edit']);

        return view('system-settings.edit', [
            'setting' => $system_setting,
        ]);
    }

    public function update(\App\Http\Requests\UpdateSettingRequest $request, \App\Models\Setting $system_setting)
    {
        Gate::authorize(Setting::getPermissions()['edit']);

        $system_setting->update($request->validated());

        return redirect()->route('system-settings.index')->with('success', 'Setting updated successfully.');
    }

    public function destroy(\App\Models\Setting $system_setting)
    {
        Gate::authorize(Setting::getPermissions()['delete']);

        $system_setting->delete();

        return redirect()->route('system-settings.index')->with('success', 'Setting deleted successfully.');
    }
}
