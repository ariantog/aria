@extends('layouts.app')

@section('title', 'Unggah Absensi')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Absensi', 'href' => route('absensi.index')],
    ['title' => 'Unggah', 'href' => '#'],
];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('absensi.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-2xl font-bold">Unggah file fingerprint</h1>
    </div>

    <div class="max-w-xl rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4"><h2 class="font-semibold">File Excel mesin absensi</h2></div>
        <form method="POST" action="{{ route('absensi.store') }}" enctype="multipart/form-data" class="space-y-4 p-6" data-testid="absensi-upload-form">
            @csrf
            <p class="text-sm text-gray-600">Hanya sheet <span class="font-medium">Lap. Log Absen</span> yang dibaca. Angka di header adalah tanggal; isi sel adalah jam masuk/pulang (contoh <code>08:2416:08</code>). ID mesin harus sudah diisi di karyawan (pencarian tidak case-sensitive).</p>
            <div class="space-y-2">
                <label class="text-sm font-medium">File .xls / .xlsx</label>
                <input type="file" name="file" accept=".xls,.xlsx" class="w-full text-sm" data-testid="absensi-file">
                @error('file')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end gap-2 border-t pt-4">
                <a href="{{ route('absensi.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Impor</button>
            </div>
        </form>
    </div>
</div>
@endsection
