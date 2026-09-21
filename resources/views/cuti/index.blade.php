@extends('layouts.app')

@section('title', 'Daftar Cuti')

@section('content')
@php
$breadcrumbs = [['title' => 'Cuti', 'href' => route('cuti.index')]];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Daftar Cuti</h1>
            <p class="text-gray-500">Catat cuti tahunan, sakit, mendadak, dan izin. Data ini dipakai hitung gaji bulanan.</p>
        </div>
        @if($can['create'] ?? false)
        <a href="{{ route('cuti.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" data-testid="cuti-create-link">
            Tambah Cuti
        </a>
        @endif
    </div>

    <form method="GET" action="{{ route('cuti.index') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex min-w-[12rem] flex-1 flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Karyawan</label>
            <input type="text" name="karyawan" value="{{ $filters['karyawan'] ?? '' }}" placeholder="Nama atau nama absensi"
                   class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Tipe</label>
            <select name="tipe" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">Semua</option>
                @foreach($types as $value => $label)
                <option value="{{ $value }}" @selected((string) ($filters['tipe'] ?? '') === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Tahun</label>
            <input type="number" name="tahun" value="{{ $filters['tahun'] ?? '' }}" min="2000" max="2100" placeholder="{{ now()->year }}"
                   class="w-28 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
        </div>
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
        <a href="{{ route('cuti.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Karyawan</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tanggal</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tipe</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-gray-500">Lama</th>
                        @if(($can['edit'] ?? false) || ($can['delete'] ?? false))
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($cutis as $cuti)
                    @php [$label, $color] = \App\Models\Cuti::$typeStyles[$cuti->tipe] ?? ['Lainnya', 'text-gray-600']; @endphp
                    <tr class="hover:bg-gray-50" data-testid="cuti-row-{{ $cuti->id }}">
                        <td class="px-5 py-3">
                            @if($cuti->karyawan)
                            <a href="{{ route('karyawan.show', $cuti->karyawan) }}" class="font-medium text-blue-600 hover:underline">{{ $cuti->karyawan->nama }}</a>
                            @if($cuti->karyawan->nama_absensi)
                            <div class="text-xs text-gray-500">{{ $cuti->karyawan->nama_absensi }}</div>
                            @endif
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-gray-700">
                            {{ \Carbon\Carbon::parse($cuti->tgl_mulai)->translatedFormat('d M Y') }}
                            @if($cuti->tgl_mulai != $cuti->tgl_akhir)
                            – {{ \Carbon\Carbon::parse($cuti->tgl_akhir)->translatedFormat('d M Y') }}
                            @endif
                        </td>
                        <td class="px-5 py-3 font-medium {{ $color }}">{{ $label }}</td>
                        <td class="px-5 py-3 text-center">{{ $cuti->total_cuti }} hari</td>
                        @if(($can['edit'] ?? false) || ($can['delete'] ?? false))
                        <td class="px-5 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                @if($can['edit'] ?? false)
                                <a href="{{ route('cuti.edit', $cuti) }}" class="text-sm font-medium text-blue-600 hover:underline">Edit</a>
                                @endif
                                @if($can['delete'] ?? false)
                                <form method="POST" action="{{ route('cuti.destroy', $cuti) }}" onsubmit="return confirm('Hapus catatan cuti ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Hapus</button>
                                </form>
                                @endif
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500">Belum ada catatan cuti.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $cutis, 'label' => 'cuti'])
    </div>
</div>
@endsection
