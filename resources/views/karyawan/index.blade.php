@extends('layouts.app')

@section('title', 'Karyawan List')

@section('content')
@php
$breadcrumbs = [['title' => 'Karyawan', 'href' => route('karyawan.index')]];
$user = auth()->user();
$isSuper = $user && $user->is_superadmin;
$now = now();
$currentMonth = $now->month;
$currentYear = $now->year;
@endphp

<div class="flex flex-col gap-4 overflow-x-auto p-4">
    <div class="mb-4 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-zinc-900">Karyawan List</h1>
            <p class="text-zinc-500">Manage employee records, salary and leaves.</p>
        </div>
        @if($isSuper || $user->can('karyawan-create'))
        <a href="{{ route('karyawan.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Baru
        </a>
        @endif
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('karyawan.index') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Search Name</label>
            <input type="text" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Employee name…" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
        </div>
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
        <a href="{{ route('karyawan.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
    </form>

    <div class="flex flex-col gap-2 text-sm md:flex-row md:space-x-3">
        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-blue-500"></span><span class="text-zinc-600">Cuti Tahunan</span></div>
        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-yellow-400"></span><span class="text-zinc-600">Cuti Sakit</span></div>
        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-red-500"></span><span class="text-zinc-600">Cuti Mendadak</span></div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="px-6 py-4 font-bold tracking-wider">Name / ID</th>
                        <th class="px-6 py-4 font-bold tracking-wider">Contact Info</th>
                        <th class="px-6 py-4 text-right font-bold tracking-wider text-zinc-900">Gaji {{ $currentMonth }}/{{ $currentYear }}</th>
                        <th class="px-6 py-4 text-right font-bold tracking-wider text-zinc-900">GPU {{ $currentMonth }}/{{ $currentYear }}</th>
                        <th class="px-6 py-4 text-center font-bold tracking-wider text-zinc-900">Cuti {{ $currentYear }}</th>
                        <th class="px-6 py-4 text-right font-bold italic tracking-wider text-zinc-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse($karyawans as $item)
                        @php $gpu = $item->gajiSingle ? ((float)$item->gajiSingle->bulanan + (float)$item->gajiSingle->harian + (float)$item->gajiSingle->premi) : 0; @endphp
                        <tr class="transition-colors hover:bg-zinc-50">
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <a href="{{ route('karyawan.show', $item->id) }}" class="flex items-center gap-1 font-semibold text-blue-600 hover:text-blue-800">
                                        {{ $item->nama }}
                                        <svg class="h-3 w-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                    <div class="mt-1 flex items-center gap-2">
                                        <span class="text-xs text-zinc-500 opacity-80">ID: {{ $item->id }}</span>
                                        @if($item->flag == 2)
                                        <span class="h-4 rounded border border-red-200 bg-red-50 px-1 text-[10px] font-bold uppercase text-red-600">Privasi</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2 text-zinc-500">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    {{ $item->no_telp ?: '-' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="font-medium text-zinc-900">
                                    @if($item->gajiSingle) Rp {{ format_amount($item->gajiSingle->total_gaji, 0) }}
                                    @else <span class="text-xs italic text-zinc-400">Belum dibuat</span> @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="font-medium text-zinc-900">
                                    @if($item->gajiSingle) Rp {{ format_amount($gpu, 0) }}
                                    @else <span class="text-xs italic text-zinc-400">Belum dibuat</span> @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <span title="Cuti Tahunan" class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-500 text-xs font-medium text-white">{{ (int)($item->total_cuti_tahunan ?? 0) }}</span>
                                    <span title="Cuti Sakit" class="flex h-7 w-7 items-center justify-center rounded-full bg-yellow-400 text-xs font-medium text-yellow-950">{{ (int)($item->total_cuti_sakit ?? 0) }}</span>
                                    <span title="Cuti Mendadak" class="flex h-7 w-7 items-center justify-center rounded-full bg-red-500 text-xs font-medium text-white">{{ (int)($item->total_cuti_mendadak ?? 0) }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2 text-xs">
                                    @if($isSuper || $user->can('karyawan-cuti-create'))
                                    <a href="{{ route('karyawan.cuti.create', $item->id) }}" class="inline-flex items-center rounded-md px-2 py-1.5 font-medium text-blue-600 hover:bg-blue-50">+ Cuti</a>
                                    @endif
                                    @if($isSuper || $user->can('karyawan-gaji-create'))
                                    <a href="{{ route('karyawan.gaji.create', $item->id) }}" class="inline-flex items-center rounded-md px-2 py-1.5 font-medium text-emerald-600 hover:bg-emerald-50">+ Gaji</a>
                                    @endif
                                    @if($isSuper || ($user->can('karyawan-edit') && $item->flag != 2))
                                    <a href="{{ route('karyawan.edit', $item->id) }}" class="inline-flex items-center rounded-md p-2 text-zinc-400 hover:bg-blue-50 hover:text-blue-500">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    @endif
                                    @if($isSuper || $user->can('karyawan-delete'))
                                    <form method="POST" action="{{ route('karyawan.destroy', $item->id) }}" onsubmit="return confirm('Are you sure you want to delete this Karyawan?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="inline-flex items-center rounded-md p-2 text-zinc-400 hover:bg-red-50 hover:text-red-500">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-zinc-500">Data Karyawan kosong.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $karyawans, 'label' => 'Karyawan'])
    </div>
</div>
@endsection
