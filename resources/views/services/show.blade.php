@extends('layouts.app')

@section('title', $item->name)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => route('items.index')],
    ['title' => 'Services', 'href' => route('services.index')],
    ['title' => $item->code, 'href' => '#'],
];
@endphp

<div class="mx-auto max-w-3xl p-4 space-y-4">
    @if(session('success'))
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">{{ $item->name }}</h2>
            <p class="font-mono text-sm text-gray-500">{{ $item->code }}</p>
        </div>
        <div class="flex gap-2">
            @if($can['edit'])
            <a href="{{ route('services.edit', $item) }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50">Edit</a>
            @endif
            @if($can['delete'])
            <form method="POST" action="{{ route('services.destroy', $item) }}" onsubmit="return confirm('Delete this service?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50">Delete</button>
            </form>
            @endif
        </div>
    </div>

    <dl class="grid gap-3 rounded-xl border border-gray-200 bg-white p-4 text-sm sm:grid-cols-2">
        <div><dt class="text-gray-500">Harga jual</dt><dd class="font-medium tabular-nums">Rp {{ format_amount($item->price, 0) }}</dd></div>
        <div><dt class="text-gray-500">Cost</dt><dd class="font-medium tabular-nums">Rp {{ format_amount($item->cost, 0) }}</dd></div>
        <div><dt class="text-gray-500">Stok</dt><dd>{{ $item->tracksInventory() ? 'Tracked' : 'Tanpa stok (unlimited)' }}</dd></div>
        <div><dt class="text-gray-500">Qty</dt><dd>{{ $item->allowsDecimalQuantity() ? 'Desimal (max 2)' : 'Bulat' }}</dd></div>
    </dl>

    @if(trim($item->catalogDescription()) !== '')
    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm">
        <h3 class="mb-1 font-semibold text-gray-900">Deskripsi</h3>
        <p class="whitespace-pre-wrap text-gray-700">{{ $item->catalogDescription() }}</p>
    </div>
    @endif
</div>
@endsection
