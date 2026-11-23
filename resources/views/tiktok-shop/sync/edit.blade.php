@extends('layouts.app')

@section('title', 'TikTok Shop — Edit mapping')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'TikTok mapping', 'href' => route('tiktok-shop.sync.index')],
    ['title' => 'Edit', 'href' => route('tiktok-shop.sync.edit', $sync->id)],
];
$warehouseRoute = route('transactions.lookup', ['type' => 'sell', 'role' => 'sender', 'addrbook_type' => $addrbookTypes['warehouse']]);
@endphp

<div class="max-w-2xl space-y-6 p-4" data-testid="tiktok-shop-sync-edit">
    <h1 class="text-2xl font-bold text-gray-900">Edit mapping TikTok Shop</h1>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('tiktok-shop.sync.update', $sync->id) }}" class="space-y-5">
            @csrf
            @method('PATCH')
            <div>
                <label for="shop_ref" class="mb-1 block text-sm font-medium text-gray-700">shop_ref / shop_id (webhook)</label>
                <input type="text" name="shop_ref" id="shop_ref" value="{{ old('shop_ref', $sync->shop_ref) }}" required maxlength="64" class="w-full rounded-md border-gray-300 font-mono text-sm">
                @error('shop_ref')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="shop_cipher" class="mb-1 block text-sm font-medium text-gray-700">shop_cipher (opsional)</label>
                <input type="text" name="shop_cipher" id="shop_cipher" value="{{ old('shop_cipher', $sync->shop_cipher) }}" maxlength="64" class="w-full rounded-md border-gray-300 font-mono text-sm">
            </div>
            <div>
                <label for="shop_name" class="mb-1 block text-sm font-medium text-gray-700">Nama toko (opsional)</label>
                <input type="text" name="shop_name" id="shop_name" value="{{ old('shop_name', $sync->shop_name) }}" maxlength="255" class="w-full rounded-md border-gray-300 text-sm">
            </div>
            <input type="hidden" name="warehouse_id" id="warehouse_id" value="{{ old('warehouse_id', $sync->warehouse_id) }}">
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Gudang Aria</label>
                @include('jubelio.partials.lookup-combobox', [
                    'endpoint' => $warehouseRoute,
                    'placeholder' => 'Cari gudang…',
                    'hiddenField' => 'warehouse_id',
                    'initialId' => old('warehouse_id', $sync->warehouse_id),
                    'initialName' => $sync->warehouse->name ?? null,
                ])
                @error('warehouse_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('tiktok-shop.sync.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Batal</a>
                <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Simpan</button>
            </div>
        </form>
    </div>

    <form method="POST" action="{{ route('tiktok-shop.sync.delete', $sync->id) }}" onsubmit="return confirm('Hapus mapping ini?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm text-red-700 hover:bg-red-50">Hapus mapping</button>
    </form>
</div>
@endsection