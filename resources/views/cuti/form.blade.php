@extends('layouts.app')

@php
    $isEdit = isset($cuti) && $cuti;
    $title = $isEdit ? 'Edit Cuti' : 'Tambah Cuti';
    $selectedKaryawanId = old('karyawan_id', $karyawan?->id);
@endphp
@section('title', $title.($karyawan ? ' - '.$karyawan->nama : ''))

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Cuti', 'href' => route('cuti.index')],
];
if ($karyawan) {
    $breadcrumbs[] = ['title' => $karyawan->nama, 'href' => route('karyawan.show', $karyawan->id)];
}
$breadcrumbs[] = ['title' => $title, 'href' => '#'];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="mb-4 flex items-center gap-4">
        <a href="{{ $karyawan ? route('karyawan.show', $karyawan->id) : route('cuti.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-2xl font-bold">{{ $title }}@if($karyawan) — {{ $karyawan->nama }}@endif</h1>
    </div>

    <div class="max-w-2xl rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4"><h2 class="font-semibold">Formulir Cuti / Izin</h2></div>
        <div class="p-6">
            <form method="POST"
                  action="{{ $isEdit ? route('cuti.update', $cuti) : ($karyawan ? route('karyawan.cuti.store', $karyawan) : route('cuti.store')) }}"
                  class="space-y-6"
                  data-testid="cuti-form">
                @csrf
                @if($isEdit) @method('PUT') @endif

                @if(! $isEdit && ! $karyawan)
                <div class="space-y-2">
                    <label class="text-sm font-medium">Karyawan</label>
                    <select name="karyawan_id" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="">Pilih karyawan</option>
                        @foreach($karyawans as $option)
                            <option value="{{ $option->id }}" @selected((string) $selectedKaryawanId === (string) $option->id)>
                                {{ $option->nama }}@if($option->nama_absensi) ({{ $option->nama_absensi }})@endif
                            </option>
                        @endforeach
                    </select>
                    @error('karyawan_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                @endif

                <div class="space-y-2">
                    <label class="text-sm font-medium">Tipe</label>
                    <select name="tipe" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="1" @selected((string) old('tipe', $cuti->tipe ?? '1') === '1')>Cuti Tahunan (kuota, tidak potong harian kecuali melebihi batas)</option>
                        <option value="2" @selected((string) old('tipe', $cuti->tipe ?? '') === '2')>Cuti Sakit (kuota, tidak potong harian kecuali melebihi batas)</option>
                        <option value="3" @selected((string) old('tipe', $cuti->tipe ?? '') === '3')>Mendadak (potong tarif harian)</option>
                        <option value="4" @selected((string) old('tipe', $cuti->tipe ?? '') === '4')>Izin (potong tarif harian)</option>
                    </select>
                    <p class="text-xs text-gray-500">Kuota tahunan/sakit diatur di Pengaturan Sistem → HR. Mendadak dan izin memotong tarif harian di gaji bulan terkait.</p>
                    @error('tipe')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Tanggal Mulai</label>
                        <input type="date" name="tgl_mulai" value="{{ old('tgl_mulai', $cuti?->tgl_mulai ? \Carbon\Carbon::parse($cuti->tgl_mulai)->toDateString() : '') }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('tgl_mulai')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Tanggal Akhir</label>
                        <input type="date" name="tgl_akhir" value="{{ old('tgl_akhir', $cuti?->tgl_akhir ? \Carbon\Carbon::parse($cuti->tgl_akhir)->toDateString() : '') }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('tgl_akhir')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <a href="{{ $karyawan ? route('karyawan.show', $karyawan->id) : route('cuti.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Batal</a>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Cuti' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
