@extends('layouts.app')

@section('title', 'Detail Karyawan - ' . $karyawan->nama)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Karyawan', 'href' => route('karyawan.index')],
    ['title' => $karyawan->nama, 'href' => route('karyawan.show', $karyawan->id)],
];
$user = auth()->user();
$isSuper = $user && $user->hasRole('superadmin');
$fmt = fn($v) => number_format((float)($v ?? 0), 0, ',', '.');
$tipeCuti = [1 => ['Tahunan','text-blue-600'], 2 => ['Sakit','text-orange-600'], 3 => ['Mendadak/Izin','text-red-600']];
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div class="flex items-center gap-4">
            <a href="{{ route('karyawan.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold">{{ $karyawan->nama }}</h1>
                <p class="text-gray-500">{{ $karyawan->no_telp }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if($isSuper || ($user->can('karyawan-edit') && $karyawan->flag != 2))
            <a href="{{ route('karyawan.edit', $karyawan->id) }}" class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Edit</a>
            @endif
            @if($isSuper || ($user->can('karyawan-gaji-create') && ($isSuper || $karyawan->flag != 2)))
            <a href="{{ route('karyawan.gaji.create', $karyawan->id) }}" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Bikin Gaji</a>
            @endif
            @if($isSuper || ($user->can('karyawan-cuti-create') && ($isSuper || $karyawan->flag != 2)))
            <a href="{{ route('karyawan.cuti.create', $karyawan->id) }}" class="inline-flex items-center gap-2 rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-200">Tambah Cuti</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm md:col-span-1">
            <div class="border-b border-gray-100 px-6 py-4"><h2 class="font-semibold">Profil Karyawan</h2></div>
            <div class="space-y-4 p-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Alamat</h4>
                    <p class="mt-1 leading-relaxed">{{ $karyawan->alamat ?: '-' }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4 border-t border-gray-100 pt-4">
                    <div><h4 class="text-sm font-medium text-gray-500">Bank</h4><p class="mt-1 font-medium">{{ $karyawan->bank->name ?? 'Kas Tunai' }}</p></div>
                    <div><h4 class="text-sm font-medium text-gray-500">Privasi</h4><p class="mt-1 font-medium">{{ $karyawan->flag == 1 ? 'Publik' : 'Private' }}</p></div>
                </div>
                <div class="space-y-2 border-t border-gray-100 pt-4">
                    <div class="flex justify-between"><span class="text-sm text-gray-500">Gaji Bulanan</span><span class="font-medium">{{ $fmt($karyawan->bulanan) }}</span></div>
                    <div class="flex justify-between"><span class="text-sm text-gray-500">Gaji Harian</span><span class="font-medium">{{ $fmt($karyawan->harian) }}</span></div>
                    <div class="flex justify-between"><span class="text-sm text-gray-500">Premi / Tunjangan</span><span class="font-medium">{{ $fmt($karyawan->premi) }}</span></div>
                </div>
            </div>
        </div>

        <div class="space-y-6 md:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="font-semibold">Riwayat Gaji</h2>
                    <p class="text-sm text-gray-500">Gaji bulan-bulan terakhir</p>
                </div>
                <div class="max-h-[300px] overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-100">
                            <tr class="border-b">
                                <th class="h-10 px-4 text-left font-medium text-gray-500">Periode</th>
                                <th class="h-10 px-4 text-right font-medium text-gray-500">Total Gaji</th>
                                <th class="h-10 px-4 text-center font-medium text-gray-500">Cuti (T/S/M)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($karyawan->gaji as $gaji)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-4 font-medium">Bulan {{ $gaji->bulan }} / {{ $gaji->tahun }}</td>
                                <td class="p-4 text-right font-bold text-green-700">{{ $fmt($gaji->total_gaji) }}</td>
                                <td class="p-4 text-center text-gray-500">{{ (int)($gaji->cuti_tahunan ?? 0) }} / {{ (int)($gaji->cuti_sakit ?? 0) }} / {{ (int)($gaji->cuti_mendadak ?? 0) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="p-4 text-center text-gray-500">Belum ada histori gaji.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="font-semibold">Riwayat Cuti</h2>
                    <p class="text-sm text-gray-500">Cuti yang pernah diambil</p>
                </div>
                <div class="max-h-[300px] overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-100">
                            <tr class="border-b">
                                <th class="h-10 px-4 text-left font-medium text-gray-500">Tanggal</th>
                                <th class="h-10 px-4 text-left font-medium text-gray-500">Tipe</th>
                                <th class="h-10 px-4 text-center font-medium text-gray-500">Lama</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($karyawan->cuti as $cuti)
                            @php [$label, $color] = $tipeCuti[$cuti->tipe] ?? ['Lainnya', 'text-gray-600']; @endphp
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-4">
                                    {{ \Carbon\Carbon::parse($cuti->tgl_mulai)->translatedFormat('d M Y') }}
                                    @if($cuti->tgl_mulai != $cuti->tgl_akhir) - {{ \Carbon\Carbon::parse($cuti->tgl_akhir)->translatedFormat('d M Y') }}@endif
                                </td>
                                <td class="p-4 font-medium {{ $color }}">{{ $label }}</td>
                                <td class="p-4 text-center">{{ (int)($cuti->tahunan ?? 0) + (int)($cuti->sakit ?? 0) + (int)($cuti->mendadak ?? 0) }} Hari</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="p-4 text-center text-gray-500">Belum history pernah cuti.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
