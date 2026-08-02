@extends('layouts.app')

@section('title', 'Edit Setoran ' . $produksi->serial)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Produksi', 'href' => route('produksi.index')],
    ['title' => 'Setoran', 'href' => route('produksi.setoran.index')],
    ['title' => $produksi->serial, 'href' => '#'],
];
$canEdit = $can['edit_setoran'];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-extrabold tracking-tight text-zinc-900">Edit Setoran</h2>
            <div class="mt-2 flex items-center gap-2 text-zinc-500">
                <span class="font-mono text-lg font-bold text-blue-600">{{ $produksi->serial }}</span>
                <span>&bull;</span>
                <span class="font-semibold">{{ $produksi->temp_name }}</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        {{-- Left: read-only details --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm lg:col-span-1">
            <h3 class="mb-4 flex items-center gap-2 text-lg font-semibold">
                <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Details
            </h3>
            <div class="space-y-6">
                <div>
                    <p class="text-xs uppercase text-zinc-500">Quantity</p>
                    <p class="text-lg font-bold">{{ $produksi->quantity }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-zinc-500">Size</p>
                    <p class="text-md font-semibold">{{ $produksi->size->name ?? '-' }}</p>
                </div>
                <div class="border-t border-zinc-100 pt-4">
                    <p class="text-xs uppercase text-zinc-500">Cutting Stage</p>
                    <div class="mt-1 flex items-center gap-2 text-sm">
                        <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>{{ $produksi->potong_date ? $produksi->potong_date->format('Y-m-d') : '-' }}</span>
                    </div>
                    <div class="mt-1 flex items-center gap-2 text-sm font-medium">
                        <svg class="h-4 w-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13 13 0 0112 15c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>{{ $produksi->potong->name ?? 'Unknown' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: edit forms --}}
        <div class="space-y-8 lg:col-span-2">
            @include('produksi.setoran._basic-form', ['produksi' => $produksi, 'canEdit' => $canEdit])
            @include('produksi.setoran._worker-forms', ['produksi' => $produksi, 'canEdit' => $canEdit])

            @if($produksi->status === \App\Models\Produksi::STATUS_SETOR)
            <div class="rounded-xl border border-l-4 border-zinc-200 border-l-red-500 bg-white p-6 shadow-sm">
                <h3 class="flex items-center gap-2 text-lg font-semibold">
                    <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Status Management
                </h3>
                <p class="mt-1 text-sm text-zinc-500">Revert this entry's status back to the production stage.</p>
                <form method="POST" action="{{ route('produksi.setoran.status-produksi', $produksi->id) }}" class="mt-4" onsubmit="return confirm('Are you sure you want to revert this to Produksi status?')">
                    @csrf
                    @method('PATCH')
                    <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                        <p class="text-sm text-zinc-600">Return this item to the production list for further processing.</p>
                        <button type="submit" @unless($canEdit) disabled @endunless class="rounded-md bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50">Kembalikan ke Produksi</button>
                    </div>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
