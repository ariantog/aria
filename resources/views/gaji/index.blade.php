@extends('layouts.app')

@section('title', 'Payroll Management')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Payroll', 'href' => route('gaji.index')],
    ['title' => 'Monthly Salary', 'href' => route('gaji.index')],
];
$user = auth()->user();
$isSuper = $user && $user->hasRole('superadmin');
$canDelete = $isSuper || $user->can('karyawan-gaji-delete');
$fmt = fn($v) => number_format((float)($v ?? 0), 0, ',', '.');
$monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$grandTotalBank = $gajiPerBank->sum('total_gaji');
$search = $filters['karyawan'] ?? '';
@endphp

<div class="flex flex-col gap-4 overflow-x-auto p-4">
    <div class="mb-4 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Monthly Salary Management</h2>
            <p class="mt-1 text-sm text-zinc-500">Track and manage employee salary disbursements for {{ $monthNames[(int)$bulanSelect - 1] }} {{ $yearSelect }}.</p>
        </div>
    </div>

    @if($isSuper)
    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-blue-100 bg-blue-50/30 p-4">
            <div class="flex items-center justify-between pb-2"><span class="text-xs font-bold uppercase text-blue-600">Grand Total Payroll</span></div>
            <div class="text-2xl font-bold text-blue-700">IDR {{ $fmt($grandTotalBank) }}</div>
            <p class="text-[10px] text-blue-600/60">Combined across all banks</p>
        </div>
        @foreach($gajiPerBank as $bank)
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between pb-2"><span class="text-xs font-bold uppercase text-zinc-500">{{ $bank->bank->name ?? 'Kas Tunai' }}</span></div>
            <div class="text-2xl font-bold text-zinc-900">IDR {{ $fmt($bank->total_gaji) }}</div>
            <p class="text-[10px] text-zinc-400">Total disbursement</p>
        </div>
        @endforeach
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between pb-2"><span class="text-xs font-bold uppercase text-zinc-500">Total Employees</span></div>
            <div class="text-2xl font-bold text-zinc-900">{{ $gajiList->total() }}</div>
            <p class="text-[10px] text-zinc-400">Processed this month</p>
        </div>
    </div>
    @endif

    <form method="GET" action="{{ route('gaji.index') }}" class="mb-2 flex flex-col gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm md:flex-row md:items-center">
        <div class="flex items-center gap-3">
            <select name="bulan" onchange="this.form.submit()" class="w-[160px] rounded-md border border-gray-300 px-3 py-2 text-sm">
                @foreach($monthNames as $i => $m)
                <option value="{{ $i + 1 }}" @selected((int)$bulanSelect === $i + 1)>{{ $m }}</option>
                @endforeach
            </select>
            <input type="number" name="tahun" value="{{ $yearSelect }}" class="w-[100px] rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div class="hidden h-8 w-px bg-zinc-200 md:block"></div>
        <div class="relative flex flex-1 items-center gap-2">
            <input type="text" name="karyawan" value="{{ $search }}" placeholder="Search employee name..." class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Search</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white text-sm shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-zinc-50/50">
                    <tr class="text-left text-xs uppercase tracking-wider text-gray-500">
                        <th class="px-4 py-3 font-bold">Periode</th>
                        <th class="px-4 py-3 font-bold">Karyawan</th>
                        <th class="px-4 py-3 text-right font-bold">Bulanan</th>
                        <th class="px-4 py-3 text-right font-bold">Total Harian</th>
                        <th class="px-4 py-3 text-right font-bold">Premi</th>
                        <th class="px-4 py-3 text-right font-bold">Potongan Cuti</th>
                        <th class="px-4 py-3 text-right font-bold">Bonus</th>
                        <th class="px-4 py-3 text-right font-bold">Sanksi</th>
                        <th class="px-4 py-3 text-right font-bold">Total Gaji</th>
                        <th class="px-4 py-3 font-bold">Account Bank</th>
                        @if($canDelete)<th class="px-4 py-3 text-center font-bold">Actions</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($gajiList as $item)
                    <tr class="hover:bg-zinc-50/50">
                        <td class="whitespace-nowrap px-4 py-3">{{ $item->bulan }}/{{ $item->tahun }}</td>
                        <td class="px-4 py-3"><a href="{{ route('karyawan.show', $item->karyawan_id) }}" class="font-semibold text-blue-600 hover:underline">{{ $item->karyawan->nama ?? '-' }}</a></td>
                        <td class="px-4 py-3 text-right tabular-nums text-zinc-700">{{ $fmt($item->bulanan) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-zinc-700">{{ $fmt($item->harian) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-zinc-700">{{ $item->potongan_cuti_premi > 0 ? '0' : $fmt($item->premi) }}</td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums text-rose-600">{{ $fmt($item->potongan_cuti_bulanan) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-emerald-600">{{ $fmt($item->bonus ?? 0) }}</td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums text-rose-600">{{ $fmt($item->sanksi ?? 0) }}</td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums text-emerald-600">{{ $fmt($item->total_gaji) }}</td>
                        <td class="px-4 py-3">
                            <span class="whitespace-nowrap rounded-full border px-2 py-0.5 text-xs {{ $item->bankSingle?->name ? 'border-gray-200' : 'border-amber-200 bg-amber-50 text-amber-700' }}">{{ $item->bankSingle->name ?? 'Kas Tunai' }}</span>
                        </td>
                        @if($canDelete)
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <a href="/gaji/cetak/{{ $item->id }}" target="_blank" title="Print Slip" class="flex h-8 w-8 items-center justify-center rounded text-zinc-400 hover:text-zinc-900">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('gaji.destroy', $item->id) }}" onsubmit="return confirm('Are you sure you want to delete this payroll record?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Delete Record" class="flex h-8 w-8 items-center justify-center rounded text-zinc-400 hover:text-rose-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ $canDelete ? 11 : 10 }}" class="h-32 px-4 py-8 text-center text-gray-500">No payroll records found for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $gajiList, 'label' => 'payroll records'])
    </div>
</div>
@endsection
