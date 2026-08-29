@extends('layouts.app')

@section('title', 'Daftar Gaji Bulanan')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'SDM', 'href' => route('gaji.index')],
    ['title' => 'Gaji Bulanan', 'href' => route('gaji.index')],
];
$user = auth()->user();
$isSuper = $user && $user->is_superadmin;
$canDelete = $isSuper || $user->can('karyawan-gaji-delete');
$canEdit = $isSuper || $user->can('karyawan-gaji-edit');
$fmt = fn($v) => format_amount($v ?? 0, 0);
$monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$grandTotalBank = $gajiPerBank->sum('total_gaji');
$search = $filters['karyawan'] ?? '';
@endphp

<div class="flex flex-col gap-4 overflow-x-auto p-4">
    <div class="mb-4 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Kelola Gaji Bulanan</h2>
            <p class="mt-1 text-sm text-gray-500">Gaji bulanan karyawan untuk {{ $monthNames[(int)$bulanSelect - 1] }} {{ $yearSelect }}.</p>
        </div>
    </div>

    @if($isSuper)
    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-blue-100 bg-blue-50/30 p-4">
            <div class="flex items-center justify-between pb-2"><span class="text-xs font-bold uppercase text-blue-600">Total Gaji</span></div>
            <div class="text-2xl font-bold text-blue-700">Rp {{ $fmt($grandTotalBank) }}</div>
            <p class="text-[10px] text-blue-600/60">Semua rekening</p>
        </div>
        @foreach($gajiPerBank as $bank)
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between pb-2"><span class="text-xs font-bold uppercase text-gray-500">{{ $bank->bank->name ?? 'Kas Tunai' }}</span></div>
            <div class="text-2xl font-bold text-gray-900">Rp {{ $fmt($bank->total_gaji) }}</div>
            <p class="text-[10px] text-gray-400">Total pembayaran</p>
        </div>
        @endforeach
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between pb-2"><span class="text-xs font-bold uppercase text-gray-500">Jumlah Karyawan</span></div>
            <div class="text-2xl font-bold text-gray-900">{{ $gajiList->total() }}</div>
            <p class="text-[10px] text-gray-400">Sudah diproses bulan ini</p>
        </div>
    </div>
    @endif

    <form method="GET" action="{{ route('gaji.index') }}" class="mb-2 flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:flex-row md:items-center">
        <div class="flex items-center gap-3">
            <select name="bulan" onchange="this.form.submit()" class="w-[160px] rounded-md border border-gray-300 px-3 py-2 text-sm">
                @foreach($monthNames as $i => $m)
                <option value="{{ $i + 1 }}" @selected((int)$bulanSelect === $i + 1)>{{ $m }}</option>
                @endforeach
            </select>
            <input type="number" name="tahun" value="{{ $yearSelect }}" class="w-[100px] rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div class="hidden h-8 w-px bg-gray-200 md:block"></div>
        <div class="relative flex flex-1 items-center gap-2">
            <input type="text" name="karyawan" value="{{ $search }}" placeholder="Cari nama karyawan…" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Cari</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white text-sm shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50/50">
                    <tr class="text-left text-xs uppercase tracking-wider text-gray-500">
                        <th class="px-4 py-3 font-bold">Periode</th>
                        <th class="px-4 py-3 font-bold">Karyawan</th>
                        <th class="px-4 py-3 text-right font-bold">Bulanan</th>
                        <th class="px-4 py-3 text-right font-bold">Harian ×26</th>
                        <th class="px-4 py-3 text-right font-bold">Lembur</th>
                        <th class="px-4 py-3 text-right font-bold">Pot. Harian</th>
                        <th class="px-4 py-3 text-right font-bold">Pot. Telat</th>
                        <th class="px-4 py-3 text-right font-bold">Bonus</th>
                        <th class="px-4 py-3 text-right font-bold">Sanksi</th>
                        <th class="px-4 py-3 text-right font-bold">Total</th>
                        <th class="px-4 py-3 font-bold">Bank</th>
                        @if($canDelete || $canEdit)<th class="px-4 py-3 text-center font-bold">Aksi</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($gajiList as $item)
                    <tr class="hover:bg-gray-50/50">
                        <td class="whitespace-nowrap px-4 py-3">{{ $item->bulan }}/{{ $item->tahun }}</td>
                        <td class="px-4 py-3"><a href="{{ route('karyawan.show', $item->karyawan_id) }}" class="font-semibold text-blue-600 hover:underline">{{ $item->karyawan->nama ?? '-' }}</a></td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $fmt($item->bulanan) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $fmt($item->harian) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-emerald-600">{{ $fmt($item->upah_lembur) }}</td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums text-rose-600">{{ $fmt($item->potongan_harian) }}</td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums text-rose-600">{{ $fmt($item->potongan_telat) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-emerald-600">{{ $fmt($item->bonus ?? 0) }}</td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums text-rose-600">{{ $fmt($item->sanksi ?? 0) }}</td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums text-emerald-600">{{ $fmt($item->total_gaji) }}</td>
                        <td class="px-4 py-3">
                            <span class="whitespace-nowrap rounded-full border px-2 py-0.5 text-xs {{ $item->bankSingle?->name ? 'border-gray-200' : 'border-amber-200 bg-amber-50 text-amber-700' }}">{{ $item->bankSingle->name ?? 'Kas Tunai' }}</span>
                        </td>
                        @if($canDelete || $canEdit)
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                @if($canEdit)
                                <a href="{{ route('gaji.edit', $item) }}" title="Edit" class="flex h-8 w-8 items-center justify-center rounded text-gray-400 hover:text-blue-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @endif
                                @if($canDelete)
                                <form method="POST" action="{{ route('gaji.destroy', $item->id) }}" onsubmit="return confirm('Yakin hapus gaji ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Hapus" class="flex h-8 w-8 items-center justify-center rounded text-gray-400 hover:text-rose-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ ($canDelete || $canEdit) ? 12 : 11 }}" class="h-32 px-4 py-8 text-center text-gray-500">Belum ada gaji untuk periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $gajiList, 'label' => 'gaji'])
    </div>
</div>
@endsection
