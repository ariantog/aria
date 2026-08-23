@extends('layouts.app')

@section('title', 'Pengecekan Stok Jubelio')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Pengecekan Stok', 'href' => route('jubelio-stock-checks.index')],
];
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Pengecekan Stok Jubelio
            </h1>
            <p class="mt-1 text-sm text-gray-500">Cek stok per gudang tersinkron — bandingkan Aria vs Jubelio available, urut selisih terbesar.</p>
        </div>

        <a href="{{ route('jubelio-stock-checks.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Buat Pengecekan Baru
        </a>
    </div>

    @if($activeJob)
    <div class="flex items-center justify-between rounded-lg border border-blue-500/30 bg-blue-50 p-4 text-blue-700">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <p class="font-bold">Pengecekan Sedang Aktif (ID: {{ $activeJob->id }})</p>
                <p class="text-sm opacity-80">
                    Status: <span class="uppercase">{{ $activeJob->status }}</span>
                    | Gudang {{ $activeJob->sync_cursor }}/{{ $syncedWarehouseCount }}
                    | {{ $activeJob->per_type_limit }} item + {{ $activeJob->per_type_limit }} aset lancar/gudang
                </p>
            </div>
        </div>
        <a href="{{ route('jubelio-stock-checks.show', $activeJob->id) }}" class="rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-50">Pantau Detail</a>
    </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 font-semibold text-gray-600 uppercase">
                    <tr>
                        <th class="px-6 py-4">ID</th>
                        <th class="px-6 py-4">Dibuat Pada</th>
                        <th class="px-6 py-4 text-center">Gudang</th>
                        <th class="px-6 py-4 text-center">SKU/gudang</th>
                        <th class="px-6 py-4 text-center">Ketidakcocokan</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($stockChecks as $job)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-mono font-bold">#{{ $job->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ \Carbon\Carbon::parse($job->created_at)->translatedFormat('d M Y H:i') }}</td>
                        <td class="px-6 py-4 text-center">{{ $job->sync_cursor }}/{{ $syncedWarehouseCount }}</td>
                        <td class="px-6 py-4 text-center">{{ $job->per_type_limit }}×2</td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $job->discrepancies_count > 0 ? 'bg-red-600 text-white' : 'border border-gray-200 bg-white text-gray-600' }}">{{ $job->discrepancies_count }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @include('jubelio.partials.stock-status-badge', ['status' => $job->status])
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('jubelio-stock-checks.show', $job->id) }}" class="flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100" title="Lihat Detail">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                                <form method="POST" action="{{ route('jubelio-stock-checks.destroy', $job->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data pengecekan ini?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50" title="Hapus">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-400 italic">Belum ada data pengecekan stok.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $stockChecks, 'label' => 'pengecekan'])
    </div>
</div>
@endsection
