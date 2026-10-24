@extends('layouts.app')

@section('title', 'Marketplace Baru — Jubelio Sync')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio Sync Mapping', 'href' => route('jubelio.sync.index')],
    ['title' => 'Marketplace Baru', 'href' => route('jubelio.sync.discover')],
];
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div class="flex items-center gap-4">
            <a href="{{ route('jubelio.sync.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold">Marketplace Baru dari Jubelio</h1>
                <p class="mt-1 text-sm text-gray-500">Channel di Jubelio yang belum punya mapping di Aria (lokasi yang sudah pernah dipakai).</p>
            </div>
        </div>
        <a href="{{ route('jubelio.sync.create') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Mapping lokasi baru
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-[10px] font-semibold text-gray-600 uppercase">
                    <tr>
                        <th class="px-6 py-4">Marketplace / Store</th>
                        <th class="px-6 py-4">Lokasi Jubelio</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($unmapped as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium">{{ $row['store_name'] }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $row['location_name'] }} <span class="text-xs text-gray-400">(#{{ $row['location_id'] }})</span></td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('jubelio.sync.create', ['location_id' => $row['location_id'], 'store_ids' => [$row['store_id']]]) }}"
                               class="inline-flex items-center rounded-lg bg-blue-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-800"
                               data-testid="jubelio-sync-add-{{ $row['store_id'] }}-{{ $row['location_id'] }}">
                                Buat mapping
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-6 py-10 text-center">
                            <p class="text-gray-600">Tidak ada marketplace baru di lokasi yang sudah dipetakan.</p>
                            <p class="mt-2 text-sm text-gray-400">Jika channel ada di lokasi Jubelio yang belum pernah dipakai di Aria, gunakan <a href="{{ route('jubelio.sync.create') }}" class="text-blue-600 hover:underline">Mapping lokasi baru</a>.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
