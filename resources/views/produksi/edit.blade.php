@extends('layouts.app')

@section('title', 'Edit Production ' . $produksi->serial)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Produksi', 'href' => route('produksi.index')],
    ['title' => 'Edit', 'href' => '#'],
    ['title' => $produksi->serial, 'href' => '#'],
];
@endphp

<div class="p-4">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Edit Production</h2>
            <div class="mt-2 flex items-center gap-2 text-gray-500">
                <span class="font-mono text-lg font-bold text-blue-600">{{ $produksi->serial }}</span>
                <span>&bull;</span>
                <span class="font-semibold">{{ $produksi->temp_name }}</span>
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-3">
        {{-- Left: read-only details --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm lg:col-span-1">
            <h3 class="mb-4 flex items-center gap-2 text-lg font-semibold">
                <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Details
            </h3>
            <div class="space-y-6">
                <div>
                    <p class="text-xs uppercase text-gray-500">Quantity</p>
                    <p class="text-lg font-bold">{{ $produksi->quantity }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-gray-500">Size</p>
                    <p class="text-md font-semibold">{{ $produksi->size->name ?? '-' }}</p>
                </div>
                <div class="border-t border-gray-100 pt-4">
                    <p class="text-xs uppercase text-gray-500">Cutting Stage</p>
                    <div class="mt-1 flex items-center gap-2 text-sm">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>{{ $produksi->potong_date ? $produksi->potong_date->format('Y-m-d') : '-' }}</span>
                    </div>
                    <div class="mt-1 flex items-center gap-2 text-sm font-medium">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13 13 0 0112 15c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>{{ $produksi->potong->name ?? 'Unknown' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: edit forms --}}
        <div class="space-y-8 lg:col-span-2">
            @include('produksi.setoran._basic-form', ['produksi' => $produksi, 'canEdit' => true])

            {{-- Split Form --}}
            <div class="rounded-xl border border-l-4 border-gray-200 border-l-orange-500 bg-white p-6 shadow-md">
                <h3 class="flex items-center gap-2 text-lg font-semibold">
                    <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Pisah Jahit (Split)
                </h3>
                <p class="mt-1 text-sm text-gray-500">Split this record into two separate entries by specifying the split quantity.</p>
                <form method="POST" action="{{ route('produksi.split', $produksi->id) }}" class="mt-4 flex flex-col items-end gap-4 sm:flex-row">
                    @csrf
                    <div class="flex-1 space-y-2">
                        <label class="text-sm font-medium">Quantity to Split</label>
                        <input type="number" name="split_q" min="1" max="{{ $produksi->quantity - 1 }}" value="{{ old('split_q') }}" placeholder="Max split value: {{ $produksi->quantity - 1 }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('split_q')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="rounded-md border border-orange-200 px-4 py-2 text-sm font-bold text-orange-700 hover:bg-orange-50">Execute Split</button>
                </form>
            </div>

            @include('produksi.setoran._jahit-form', ['produksi' => $produksi, 'canEdit' => true])
        </div>
    </div>
</div>
@endsection
