<?php

namespace App\Http\Controllers;

use App\Models\LarkOpsSettings;
use App\Models\StaffRole;
use App\Services\Lark\LarkSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LarkOpsSettingsController extends Controller
{
    public function index(LarkSettingsService $settings)
    {
        Gate::authorize(LarkOpsSettings::getPermissions()['view']);

        return view('lark-settings.index', [
            'settings' => $settings->all(),
            'eventLabels' => $settings->eventLabels(),
            'lowCoverItemTypeOptions' => LarkSettingsService::lowCoverItemTypeOptions(),
            'staffRoles' => StaffRole::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'runtime' => [
                'enabled' => (bool) config('lark.enabled'),
                'dry_run' => (bool) config('lark.dry_run'),
            ],
            'canEdit' => request()->user()?->can(LarkOpsSettings::getPermissions()['edit']) ?? false,
            'flash' => ['success' => session('success')],
        ]);
    }

    public function update(Request $request, LarkSettingsService $settings)
    {
        Gate::authorize(LarkOpsSettings::getPermissions()['edit']);

        $validated = $request->validate([
            'default_cover_days' => 'required|integer|min:1|max:365',
            'best_seller_cover_days' => 'required|integer|min:1|max:365',
            'rearm_buffer_days' => 'required|numeric|min:0|max:60',
            'mismatch_qty_delta' => 'required|integer|min:0|max:9999',
            'low_cover_alert_item_types' => 'required|array|min:1',
            'low_cover_alert_item_types.*' => 'integer|in:1,2,3',
            'error_backlog_threshold' => 'required|integer|min:1|max:99999',
            'jubelio_order_url_template' => 'nullable|string|max:500',
            'events' => 'nullable|array',
            'events.*.send_enabled' => 'nullable|in:0,1',
            'events.*.mention' => 'nullable|in:none,roster',
            'events.*.roles' => 'nullable|array',
            'events.*.roles.*' => 'string|max:64',
        ]);

        $settings->update($validated);

        return redirect()->route('lark-settings.index')->with('success', 'Pengaturan Lark disimpan.');
    }
}
