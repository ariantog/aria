@extends('layouts.app')

@section('title', 'Services')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => route('items.index')],
    ['title' => 'Services', 'href' => route('services.index')],
];
@endphp

<div class="flex flex-col gap-3 p-3 sm:p-4">
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Services</h2>
            <p class="mt-0.5 text-sm text-gray-500">Jasa &amp; layanan (tanpa stok default) — printing per m², training, dll.</p>
        </div>
        @if($can['create'])
        <a href="{{ route('services.create') }}" data-testid="services-create"
           class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            Add Service
        </a>
        @endif
    </div>

    @if(session('success'))
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('services.index') }}" class="flex flex-wrap items-end gap-2">
        <div class="grid gap-1.5">
            <label class="text-sm font-medium text-gray-700" for="search">Search</label>
            <input id="search" name="search" value="{{ $filters['search'] }}"
                   class="w-64 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" placeholder="Nama / kode">
        </div>
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm" data-testid="services-table">
            <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-3 py-2.5 font-bold">Kode</th>
                    <th class="px-3 py-2.5 font-bold">Nama</th>
                    <th class="px-3 py-2.5 text-right font-bold">Harga</th>
                    <th class="px-3 py-2.5 font-bold">Stok</th>
                    <th class="px-3 py-2.5 font-bold">Qty</th>
                    <th class="w-16 px-3 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-3 py-2 font-mono text-xs">{{ $item->code }}</td>
                    <td class="px-3 py-2">{{ $item->name }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">Rp {{ format_amount($item->price, 0) }}</td>
                    <td class="px-3 py-2 text-xs text-gray-600">{{ $item->tracksInventory() ? 'Tracked' : 'Tanpa stok' }}</td>
                    <td class="px-3 py-2 text-xs text-gray-600">{{ $item->allowsDecimalQuantity() ? 'Desimal' : 'Bulat' }}</td>
                    <td class="px-3 py-2 text-right">
                        <a href="{{ route('services.show', $item) }}" class="text-blue-600 hover:underline">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-3 py-8 text-center text-gray-500">Belum ada service.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $items->links() }}
</div>
@endsection
