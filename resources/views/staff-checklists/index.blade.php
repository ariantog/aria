@extends('layouts.app')

@section('title', 'Checklist Peran')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Checklist Peran', 'href' => route('staff-checklists.index')],
];
$summary = $overview['summary'] ?? [];
$roles = $overview['roles'] ?? [];
$users = $overview['users'] ?? [];
$unmappedRoles = $overview['unmapped_roles'] ?? [];
$usersWithoutRoles = $overview['users_without_roles'] ?? [];
$periodKeys = $overview['period_keys'] ?? [];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Checklist Peran</h2>
            <p class="mt-0.5 text-sm text-gray-500">Lihat peran operasional, pemetaan pengguna, dan progress checklist per periode.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($canManageTemplates ?? false)
            <a href="{{ route('staff-checklists.templates.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
               data-testid="manage-templates-link">
                {{ ($canEditTemplates ?? false) ? 'Kelola template' : 'Lihat template' }}
            </a>
            @endif
            @if($canAssignStaffRoles ?? false)
            <a href="{{ route('users.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Kelola pemetaan di Users →
            </a>
            @endif
        </div>
    </div>

    <form method="GET" action="{{ route('staff-checklists.index') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Tanggal acuan</label>
            <input type="date" name="date" value="{{ $filters['date'] ?? $overview['as_of'] ?? now()->toDateString() }}"
                   class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Terapkan</button>
            <a href="{{ route('staff-checklists.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Hari ini</a>
        </div>
    </form>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-500">Peran tersedia</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $summary['roles_total'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-green-200 bg-green-50 p-4 shadow-sm">
            <p class="text-xs font-medium uppercase text-green-700">Peran terpetakan</p>
            <p class="mt-1 text-2xl font-bold text-green-900">{{ $summary['roles_mapped'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
            <p class="text-xs font-medium uppercase text-amber-700">Peran belum dipetakan</p>
            <p class="mt-1 text-2xl font-bold text-amber-900">{{ $summary['roles_unmapped'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-500">Pengguna berperan</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $summary['users_with_roles'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
            <p class="text-xs font-medium uppercase text-red-700">Pengguna tanpa peran</p>
            <p class="mt-1 text-2xl font-bold text-red-900">{{ $summary['users_without_roles'] ?? 0 }}</p>
        </div>
    </div>

    @if(! empty($unmappedRoles) || ! empty($usersWithoutRoles))
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        @if(! empty($unmappedRoles))
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4" data-testid="unmapped-roles-panel">
            <h3 class="text-sm font-semibold text-amber-900">Peran belum dipetakan ke pengguna</h3>
            <ul class="mt-3 space-y-2">
                @foreach($unmappedRoles as $role)
                <li class="rounded-md border border-amber-100 bg-white px-3 py-2 text-sm">
                    <span class="font-medium text-gray-900">{{ $role['name'] }}</span>
                    <span class="text-gray-500"> — {{ $role['templates_count'] }} item checklist</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(! empty($usersWithoutRoles))
        <div class="rounded-xl border border-red-200 bg-red-50 p-4" data-testid="users-without-roles-panel">
            <h3 class="text-sm font-semibold text-red-900">Pengguna aktif tanpa peran operasional</h3>
            <ul class="mt-3 space-y-2">
                @foreach($usersWithoutRoles as $user)
                <li class="flex items-center justify-between rounded-md border border-red-100 bg-white px-3 py-2 text-sm">
                    <span>
                        <span class="font-medium text-gray-900">{{ $user['name'] }}</span>
                        <span class="text-gray-500">{{ '@'.$user['username'] }}</span>
                    </span>
                    @if($canAssignStaffRoles ?? false)
                    <a href="{{ route('users.edit', $user['id']) }}" class="text-xs font-medium text-blue-700 hover:underline">Tetapkan peran</a>
                    @endif
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="staff-roles-catalog">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="text-base font-semibold text-gray-900">Daftar peran operasional</h3>
            <p class="text-sm text-gray-500">Semua peran checklist yang tersedia dan pengguna yang ditugaskan.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Peran</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Item checklist</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Pengguna</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach($roles as $role)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-4">
                            <div class="font-medium text-gray-900">{{ $role['name'] }}</div>
                            @if($role['description'])
                            <div class="mt-0.5 text-xs text-gray-500">{{ $role['description'] }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-gray-600">{{ $role['templates_count'] }}</td>
                        <td class="px-5 py-4">
                            @if(! empty($role['users']))
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($role['users'] as $user)
                                <span class="rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs text-gray-700">{{ $user['name'] }}</span>
                                @endforeach
                            </div>
                            @else
                            <span class="text-xs text-amber-700">Belum ada pengguna</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if($role['is_mapped'])
                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Terpetakan</span>
                            @else
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Belum dipetakan</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="staff-checklist-progress">
        <div class="border-b border-gray-100 px-5 py-4">
            <h3 class="text-base font-semibold text-gray-900">Progress checklist per pengguna</h3>
            <p class="text-sm text-gray-500">
                Periode:
                harian {{ $periodKeys['daily'] ?? '—' }},
                mingguan {{ $periodKeys['weekly'] ?? '—' }},
                dwi minggu {{ $periodKeys['biweekly'] ?? '—' }},
                bulanan {{ $periodKeys['monthly'] ?? '—' }}
            </p>
        </div>

        @if(empty($users))
        <div class="px-5 py-8 text-sm text-gray-500">Belum ada pengguna aktif dengan peran operasional.</div>
        @else
        <div class="divide-y divide-gray-100">
            @foreach($users as $user)
            <div x-data="{ open: false }" class="px-5 py-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="font-semibold text-gray-900">{{ $user['name'] }}</h4>
                            <span class="text-sm text-gray-500">{{ '@'.$user['username'] }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach($user['roles'] as $role)
                            <span class="rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-800">{{ $role['name'] }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        @foreach($user['frequency_stats'] as $frequency => $stat)
                            @if($stat['total'] > 0)
                            <div class="text-center">
                                <p class="text-[10px] font-medium uppercase text-gray-500">{{ $stat['label'] }}</p>
                                <p class="text-sm font-semibold {{ $stat['completed'] === $stat['total'] ? 'text-green-700' : 'text-amber-700' }}">
                                    {{ $stat['completed'] }}/{{ $stat['total'] }}
                                </p>
                            </div>
                            @endif
                        @endforeach
                        <div class="rounded-lg border border-gray-200 px-3 py-2 text-center">
                            <p class="text-[10px] font-medium uppercase text-gray-500">Total</p>
                            <p class="text-sm font-bold text-gray-900">{{ $user['summary']['percent'] }}%</p>
                        </div>
                        <button type="button" @click="open = !open"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
                            <span x-text="open ? 'Sembunyikan detail' : 'Lihat detail'"></span>
                        </button>
                    </div>
                </div>

                <div x-show="open" x-cloak class="mt-4 overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Item</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Peran</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Frekuensi</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach($user['items'] as $item)
                            <tr>
                                <td class="px-4 py-2 text-gray-900">{{ $item['title'] }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $item['role_name'] }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $item['frequency_label'] }}</td>
                                <td class="px-4 py-2">
                                    @if($item['completed'])
                                    <span class="text-green-700">Selesai</span>
                                    @if($item['completed_at'])
                                    <span class="text-xs text-gray-500"> — {{ $item['completed_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
                                    @endif
                                    @else
                                    <span class="text-amber-700">Belum</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
