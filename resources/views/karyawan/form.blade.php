@extends('layouts.app')

@php $isEdit = isset($karyawan) && $karyawan; @endphp
@section('title', $isEdit ? 'Edit Karyawan' : 'Tambah Karyawan')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Karyawan', 'href' => route('karyawan.index')],
    ['title' => $isEdit ? 'Edit Karyawan' : 'Tambah Karyawan', 'href' => '#'],
];
$val = fn($k, $d = '') => old($k, $isEdit ? ($karyawan->$k ?? $d) : $d);
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('karyawan.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-2xl font-bold">{{ $isEdit ? 'Edit Karyawan' : 'Tambah Karyawan' }}</h1>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4"><h2 class="font-semibold">Informasi Karyawan</h2></div>
        <div class="p-6">
            <form method="POST" action="{{ $isEdit ? route('karyawan.update', $karyawan->id) : route('karyawan.store') }}" class="space-y-6">
                @csrf
                @if($isEdit) @method('PUT') @endif
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Nama Lengkap</label>
                        <input name="nama" value="{{ $val('nama') }}" placeholder="Nama Karyawan" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('nama')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">No. Telepon</label>
                        <input name="no_telp" value="{{ $val('no_telp') }}" placeholder="08123456789" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('no_telp')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <label class="text-sm font-medium">Alamat Lengkap</label>
                        <textarea name="alamat" rows="3" placeholder="Alamat domisili" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">{{ $val('alamat') }}</textarea>
                        @error('alamat')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Gaji Bulanan (Rp)</label>
                        <input name="bulanan" type="number" value="{{ $val('bulanan') }}" placeholder="Misal: 3000000" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('bulanan')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Gaji Harian (Rp)</label>
                        <input name="harian" type="number" value="{{ $val('harian') }}" placeholder="Misal: 100000" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('harian')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Premi (Rp)</label>
                        <input name="premi" type="number" value="{{ $val('premi') }}" placeholder="Misal: 500000" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('premi')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Rekening Bank</label>
                        <select name="bank_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Pilih Akun Bank</option>
                            @foreach($banks as $bank)
                            <option value="{{ $bank->id }}" @selected((string)$val('bank_id') === (string)$bank->id)>{{ $bank->name }}</option>
                            @endforeach
                        </select>
                        @error('bank_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Status Publikasi / Privasi</label>
                        <select name="flag" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="1" @selected((string)$val('flag','1') === '1')>Publik (Bisa Dilihat Kasir)</option>
                            <option value="2" @selected((string)$val('flag','1') === '2')>Private (Hanya Admin)</option>
                        </select>
                        @error('flag')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <a href="{{ route('karyawan.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Batal</a>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                        Simpan Karyawan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
