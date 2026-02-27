<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        Gate::authorize(Setting::getPermissions()['view']);

        return Inertia::render('SystemSettings/Index', [
            'settings' => Setting::latest()->get(),
        ]);
    }

    public function create()
    {
        Gate::authorize(Setting::getPermissions()['create']);

        return Inertia::render('SystemSettings/Create');
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

        return Inertia::render('SystemSettings/Edit', [
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
