@extends('layouts.app')

@section('title', 'Buat Pengecekan Stok Jubelio')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Pengecekan Stok', 'href' => route('jubelio-stock-checks.index')],
    ['title' => 'Buat Pengecekan', 'href' => route('jubelio-stock-checks.create')],
];
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('jubelio-stock-checks.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold">Buat Pengecekan Stok Baru</h1>
            <p class="text-sm text-gray-500">Per gudang tersinkron: cek SKU berdasarkan permintaan penjualan + cadangan stok di gudang.</p>
        </div>
    </div>

    <div class="max-w-2xl rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h3 class="text-sm font-semibold text-gray-900">Konfigurasi Pengecekan</h3>
        </div>
        <div class="p-6">
            @if($activeJob)
            <div class="rounded-lg bg-yellow-50 p-4 text-yellow-800">
                <p class="font-medium">Pengecekan Sedang Berjalan</p>
                <p class="mt-1 text-sm">Terdapat pengecekan (ID: {{ $activeJob->id }}) yang sedang berstatus "{{ $activeJob->status }}". Harap tunggu hingga selesai sebelum membuat pengecekan baru.</p>
                <div class="mt-4">
                    <a href="{{ route('jubelio-stock-checks.index') }}" class="inline-flex rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Kembali ke Daftar</a>
                </div>
            </div>
            @else
            <form method="POST" action="{{ route('jubelio-stock-checks.store') }}" class="space-y-6">
                @csrf
                <div class="space-y-2">
                    <label for="per_type_limit" class="block text-sm font-medium text-gray-700">SKU per tipe per gudang</label>
                    <input type="number" name="per_type_limit" id="per_type_limit" min="10" max="100" required
                           value="{{ old('per_type_limit', 50) }}"
                           class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <p class="text-xs text-gray-500">
                        {{ old('per_type_limit', 50) }} item + {{ old('per_type_limit', 50) }} aset lancar per gudang yang tersinkron ke Jubelio.
                        Dipilih berdasarkan penjualan terbanyak, lalu diisi dari stok gudang jika kurang.
                    </p>
                    @error('per_type_limit')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

                <div class="space-y-2">
                    <label for="demand_days" class="block text-sm font-medium text-gray-700">Rentang permintaan (hari)</label>
                    <input type="number" name="demand_days" id="demand_days" min="7" max="365" required
                           value="{{ old('demand_days', 90) }}"
                           class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <p class="text-xs text-gray-500">Ranking SKU berdasarkan qty penjualan dari gudang ini dalam N hari terakhir.</p>
                    @error('demand_days')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

                <div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-600">
                    <p class="font-medium text-gray-800">Perbandingan stok</p>
                    <p class="mt-1">Aria qty di gudang dibandingkan dengan Jubelio <strong>on-hand</strong> (bukan <code class="text-xs">available</code>). Kolom on-order ditampilkan hanya sebagai referensi.</p>
                </div>

                @error('active_job')<p class="text-sm text-red-500">{{ $message }}</p>@enderror

                <div class="flex justify-end gap-3 pt-4">
                    <a href="{{ route('jubelio-stock-checks.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</a>
                    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Mulai Pengecekan</button>
                </div>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
