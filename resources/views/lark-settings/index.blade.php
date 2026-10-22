@extends('layouts.app')

@section('title', 'Lark Ops Settings')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'System Settings', 'href' => route('system-settings.index')],
    ['title' => 'Lark Ops', 'href' => route('lark-settings.index')],
];
$lc = $settings['low_cover'] ?? [];
$jub = $settings['jubelio'] ?? [];
$events = $settings['events'] ?? [];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Lark Ops</h2>
        <p class="mt-0.5 text-sm text-gray-500">Ambang alert stok/Jubelio dan @ mention per jenis event. Webhook URL, secret, dan master switch tetap di <code class="text-xs">.env</code>.</p>
    </div>

    @if($flash['success'] ?? null)
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ $flash['success'] }}</div>
    @endif

    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
        <span class="font-medium">Runtime:</span>
        Lark {{ $runtime['enabled'] ? 'enabled' : 'disabled' }},
        dry-run {{ $runtime['dry_run'] ? 'on' : 'off' }}.
        Map Open ID per user di <a href="{{ route('users.index') }}" class="text-blue-700 hover:underline">Users → Edit</a> (permission <span class="font-mono text-xs">users-lark-notify-edit</span>).
    </div>

    <form method="POST" action="{{ route('lark-settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Threshold stok</h3>
            </div>
            <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Cover hari (default)</label>
                    <input type="number" name="default_cover_days" min="1" max="365" required
                           value="{{ old('default_cover_days', $lc['default_cover_days'] ?? 14) }}"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" @disabled(! $canEdit)>
                    @error('default_cover_days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Cover hari (best seller)</label>
                    <input type="number" name="best_seller_cover_days" min="1" max="365" required
                           value="{{ old('best_seller_cover_days', $lc['best_seller_cover_days'] ?? 30) }}"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" @disabled(! $canEdit)>
                    @error('best_seller_cover_days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Re-arm buffer (hari)</label>
                    <input type="number" name="rearm_buffer_days" min="0" max="60" step="0.1" required
                           value="{{ old('rearm_buffer_days', $lc['rearm_buffer_days'] ?? 4) }}"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" @disabled(! $canEdit)>
                    @error('rearm_buffer_days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Selisih qty mismatch</label>
                    <input type="number" name="mismatch_qty_delta" min="0" max="9999" required
                           value="{{ old('mismatch_qty_delta', $lc['mismatch_qty_delta'] ?? 3) }}"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" @disabled(! $canEdit)>
                    @error('mismatch_qty_delta')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            @php
                $selectedTypes = old('low_cover_alert_item_types', $lc['alert_item_types'] ?? [2]);
            @endphp
            <div class="border-t border-gray-100 px-6 py-4">
                <p class="text-sm font-medium text-gray-900">Stok rendah — jenis item</p>
                <p class="mt-1 text-xs text-gray-500">Lark mengirim alert cover rendah, selisih Aria/Jubelio (dari scan ini), dan kartu restock hanya untuk tipe yang dicentang. Default: asset lancar saja.</p>
                <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2">
                    @foreach($lowCoverItemTypeOptions as $typeId => $label)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="low_cover_alert_item_types[]" value="{{ $typeId }}"
                                   @checked(in_array($typeId, $selectedTypes, true))
                                   @disabled(! $canEdit)
                                   class="rounded border-gray-300 text-blue-600">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('low_cover_alert_item_types')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Jubelio</h3>
            </div>
            <div class="grid grid-cols-1 gap-4 p-6 lg:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Error backlog threshold</label>
                    <input type="number" name="error_backlog_threshold" min="1" max="99999" required
                           value="{{ old('error_backlog_threshold', $jub['error_backlog_threshold'] ?? 25) }}"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" @disabled(! $canEdit)>
                    @error('error_backlog_threshold')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">URL template order Jubelio</label>
                    <input type="text" name="jubelio_order_url_template" maxlength="500"
                           placeholder="https://…/{salesorder_id} atau …/{invoice}"
                           value="{{ old('jubelio_order_url_template', $settings['jubelio_order_url_template'] ?? '') }}"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono" @disabled(! $canEdit)>
                    <p class="mt-1 text-xs text-gray-500">Placeholder: <span class="font-mono">{salesorder_id}</span>, <span class="font-mono">{invoice}</span></p>
                    @error('jubelio_order_url_template')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Event → @ mention</h3>
                <p class="mt-1 text-sm text-gray-500">Matikan <span class="font-medium">Kirim Lark</span> untuk menonaktifkan kartu event tanpa mengubah scanner/cron. Roster = @ users with matching staff role, Lark Open ID, and notify enabled.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Event</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Kirim Lark</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Mention</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Staff roles</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($eventLabels as $eventKey => $label)
                            @php
                                $row = $events[$eventKey] ?? ['send_enabled' => true, 'mention' => 'none', 'roles' => []];
                                $mention = old("events.{$eventKey}.mention", $row['mention'] ?? 'none');
                                $selectedRoles = old("events.{$eventKey}.roles", $row['roles'] ?? []);
                                $sendEnabled = (bool) old("events.{$eventKey}.send_enabled", $row['send_enabled'] ?? true);
                            @endphp
                            <tr>
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium text-gray-900">{{ $label }}</div>
                                    <div class="font-mono text-xs text-gray-400">{{ $eventKey }}</div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    @if($canEdit)
                                        <input type="hidden" name="events[{{ $eventKey }}][send_enabled]" value="0">
                                    @endif
                                    <label class="inline-flex items-center gap-2 text-gray-700">
                                        <input type="checkbox" name="events[{{ $eventKey }}][send_enabled]" value="1"
                                               @checked($sendEnabled)
                                               @disabled(! $canEdit)
                                               class="rounded border-gray-300 text-blue-600">
                                        <span class="text-xs">Aktif</span>
                                    </label>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <select name="events[{{ $eventKey }}][mention]" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm" @disabled(! $canEdit)>
                                        <option value="none" @selected($mention === 'none')>None</option>
                                        <option value="roster" @selected($mention === 'roster')>Roster @</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                                        @foreach($staffRoles as $role)
                                            <label class="inline-flex items-center gap-1.5 text-gray-700">
                                                <input type="checkbox" name="events[{{ $eventKey }}][roles][]" value="{{ $role->slug }}"
                                                       @checked(in_array($role->slug, $selectedRoles, true))
                                                       @disabled(! $canEdit)
                                                       class="rounded border-gray-300 text-blue-600">
                                                {{ $role->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($canEdit)
            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Simpan</button>
            </div>
        @endif
    </form>
</div>
@endsection
