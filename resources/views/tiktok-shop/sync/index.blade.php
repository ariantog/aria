@extends('layouts.app')

@section('title', 'TikTok Shop — Warehouse mapping')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.settings.index', ['tab' => 'warehouse'])],
    ['title' => 'TikTok Shop mapping', 'href' => route('tiktok-shop.sync.index')],
];
@endphp

<div class="flex flex-col gap-6 p-4" data-testid="tiktok-shop-sync-index">
    @if($flash['success'] ?? null)
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? null)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">TikTok Shop / Tokopedia (Partner)</h1>
            <p class="mt-1 text-sm text-gray-600">Petakan <code class="text-xs">shop_id</code> webhook TikTok ke gudang Aria (Tokopedia storefront memakai jalur Partner yang sama).</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('tiktok-shop.sync.index') }}" class="flex items-center gap-2">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari shop ref / nama…" class="h-9 w-56 rounded-md border border-gray-300 px-3 text-sm">
                <button type="submit" class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800">Cari</button>
            </form>
            <a href="{{ route('tiktok-shop.sync.create') }}" class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800">Tambah mapping</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">shop_ref (webhook)</th>
                    <th class="px-4 py-3">shop_cipher</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Gudang Aria</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($dataList as $row)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs">{{ $row->shop_ref }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $row->shop_cipher ?: '—' }}</td>
                    <td class="px-4 py-3">{{ $row->shop_name ?: '—' }}</td>
                    <td class="px-4 py-3">{{ $row->warehouse->name ?? $row->warehouse_id }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('tiktok-shop.sync.edit', $row) }}" class="text-sm font-medium text-blue-700 hover:underline">Edit</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada mapping.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $dataList->links() }}</div>
</div>
@endsection