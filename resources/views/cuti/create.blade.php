@extends('layouts.app')

@section('title', 'Tambah Cuti - ' . $karyawan->nama)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Karyawan', 'href' => route('karyawan.index')],
    ['title' => $karyawan->nama, 'href' => route('karyawan.show', $karyawan->id)],
    ['title' => 'Tambah Cuti', 'href' => '#'],
];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="mb-4 flex items-center gap-4">
        <a href="{{ route('karyawan.show', $karyawan->id) }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-2xl font-bold">Tambah Cuti - {{ $karyawan->nama }}</h1>
    </div>

    <div class="max-w-2xl rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4"><h2 class="font-semibold">Formulir Cuti Baru</h2></div>
        <div class="p-6">
            <form method="POST" action="{{ route('karyawan.cuti.store', $karyawan->id) }}" class="space-y-6">
                @csrf
                <div class="space-y-2">
                    <label class="text-sm font-medium">Tipe Cuti</label>
                    <select name="tipe" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="1" @selected(old('tipe','1')==='1')>Cuti Tahunan</option>
                        <option value="2" @selected(old('tipe')==='2')>Cuti Sakit</option>
                        <option value="3" @selected(old('tipe')==='3')>Cuti Mendadak / Izin</option>
                    </select>
                    @error('tipe')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Tanggal Mulai</label>
                        <input type="date" name="tgl_mulai" value="{{ old('tgl_mulai') }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('tgl_mulai')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Tanggal Akhir</label>
                        <input type="date" name="tgl_akhir" value="{{ old('tgl_akhir') }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('tgl_akhir')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <a href="{{ route('karyawan.show', $karyawan->id) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Batal</a>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan Cuti</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
