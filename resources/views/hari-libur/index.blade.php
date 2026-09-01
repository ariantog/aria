@extends('layouts.app')

@section('title', 'Hari Libur')

@section('content')
@php
$breadcrumbs = [['title' => 'Hari Libur', 'href' => route('hari-libur.index')]];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Hari Libur</h1>
            <p class="text-gray-500">Tanggal libur nasional atau libur pabrik. Hari ini tidak dihitung sebagai jam kerja wajib. Kalau ada yang masuk, jamnya tetap dihitung (bisa menukar hari biasa / Minggu).</p>
        </div>
    </div>

    <form method="GET" action="{{ route('hari-libur.index') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Tahun</label>
            <input type="number" name="tahun" value="{{ $year }}" min="2000" max="2100"
                   class="w-28 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
        </div>
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
    </form>

    @if($can['create'] ?? false)
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4"><h2 class="font-semibold">Tambah hari libur</h2></div>
        <form method="POST" action="{{ route('hari-libur.store') }}" class="grid grid-cols-1 gap-4 p-6 md:grid-cols-4" data-testid="hari-libur-form">
            @csrf
            <div class="space-y-1">
                <label class="text-sm font-medium">Tanggal</label>
                <input type="date" name="tanggal" value="{{ old('tanggal') }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" data-testid="hari-libur-tanggal">
                @error('tanggal')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="space-y-1 md:col-span-1">
                <label class="text-sm font-medium">Nama</label>
                <input type="text" name="nama" value="{{ old('nama') }}" placeholder="Mis. Hari Kemerdekaan" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" data-testid="hari-libur-nama">
                @error('nama')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="space-y-1">
                <label class="text-sm font-medium">Catatan</label>
                <input type="text" name="catatan" value="{{ old('catatan') }}" placeholder="Opsional" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="flex items-end">
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" data-testid="hari-libur-submit">Simpan</button>
            </div>
        </form>
    </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tanggal</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Hari</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Nama</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Catatan</th>
                        @if($can['delete'] ?? false)
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($libur as $item)
                    <tr class="hover:bg-gray-50" data-testid="hari-libur-row-{{ $item->id }}">
                        <td class="px-5 py-3 font-medium">{{ $item->tanggal->translatedFormat('d M Y') }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $item->tanggal->translatedFormat('l') }}</td>
                        <td class="px-5 py-3">{{ $item->nama }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ $item->catatan ?: '—' }}</td>
                        @if($can['delete'] ?? false)
                        <td class="px-5 py-3 text-right">
                            <form method="POST" action="{{ route('hari-libur.destroy', $item) }}" onsubmit="return confirm('Hapus hari libur ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500">Belum ada hari libur untuk {{ $year }}. Tambahkan tanggal yang tidak masuk kerja (libur nasional, cuti bersama, libur pabrik).</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
