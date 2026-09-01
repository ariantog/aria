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
$waktuDibatasi = filter_var(old('waktu_dibatasi', $isEdit ? ($karyawan->waktu_dibatasi ?? true) : true), FILTER_VALIDATE_BOOLEAN);
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
                        <input name="nama" value="{{ $val('nama') }}" placeholder="Nama karyawan" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('nama')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Nama Absensi</label>
                        <input name="nama_absensi" value="{{ $val('nama_absensi') }}" placeholder="Nama di mesin fingerprint" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" data-testid="nama-absensi">
                        <p class="text-xs text-gray-500">Nama persis seperti di mesin fingerprint (opsional, untuk referensi).</p>
                        @error('nama_absensi')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">ID Absensi</label>
                        <input name="absen_id" value="{{ $val('absen_id') }}" placeholder="Core-001" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" data-testid="absen-id">
                        <p class="text-xs text-gray-500">ID di mesin fingerprint. Pencarian tidak membedakan huruf besar/kecil (Core-001 = core-001).</p>
                        @error('absen_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
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
                        <input name="bulanan" type="number" value="{{ $val('bulanan') }}" placeholder="Contoh: 3000000" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('bulanan')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Tarif Harian (Rp)</label>
                        <input name="harian" type="number" value="{{ $val('harian') }}" placeholder="Contoh: 100000" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <p class="text-xs text-gray-500">Tarif per hari × 26 hari kerja. Sudah termasuk insentif harian (premi lama digabung ke sini).</p>
                        @error('harian')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Rekening Bank</label>
                        <select name="bank_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Pilih rekening bank</option>
                            @foreach($banks as $bank)
                            <option value="{{ $bank->id }}" @selected((string)$val('bank_id') === (string)$bank->id)>{{ $bank->name }}</option>
                            @endforeach
                        </select>
                        @error('bank_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Privasi</label>
                        <select name="flag" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="1" @selected((string)$val('flag','1') === '1')>Publik (role payroll)</option>
                            <option value="2" @selected((string)$val('flag','1') === '2')>Privasi (hanya superadmin)</option>
                        </select>
                        @error('flag')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="space-y-4 border-t border-gray-100 pt-4">
                    <h3 class="text-sm font-semibold text-gray-900">Kehadiran & Jam Kerja</h3>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input type="checkbox" name="waktu_dibatasi" value="1" class="rounded border-gray-300" @checked($waktuDibatasi)>
                                Waktu dibatasi
                            </label>
                            <p class="text-xs text-gray-500">Nonaktifkan untuk penjahit borongan / jam fleksibel (tidak kena potong telat).</p>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Jam Masuk</label>
                            <input type="time" name="jam_masuk" value="{{ $val('jam_masuk', '08:00') }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            @error('jam_masuk')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Jam Kerja / Hari</label>
                            <input type="number" name="jam_kerja" value="{{ $val('jam_kerja', 8) }}" min="1" max="16" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" data-testid="jam-kerja">
                            <p class="text-xs text-gray-500">Default 8. Beberapa orang 7 atau 10. Keterlambatan tidak dihitung dari file absensi — hanya total jam (pulang − masuk).</p>
                            @error('jam_kerja')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Grace Period (menit)</label>
                            <input type="number" name="grace_period_menit" value="{{ $val('grace_period_menit') }}" min="0" max="180" placeholder="Kosong = pakai setting global" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            @error('grace_period_menit')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <a href="{{ route('karyawan.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Batal</a>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Simpan Karyawan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
