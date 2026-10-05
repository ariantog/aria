@extends('layouts.app')

@section('title', 'Shopee Warehouse Mapping')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Shopee Warehouse Mapping', 'href' => route('shopee.sync.index')],
];
@endphp

<div class="flex flex-col gap-6 p-4">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold">Shopee Warehouse Mapping</h1>
            <p class="mt-1 text-sm text-gray-500">Map Aria gudang to Shopee pickup warehouse / stock location for live sellable qty.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('shopee.sync.index') }}" class="flex items-center gap-2">
                <input type="text" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Search Shopee warehouse..." class="h-9 w-64 rounded-md border border-gray-300 px-3 text-sm focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                <button type="submit" class="rounded-lg bg-orange-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-700">Search</button>
            </form>
            @can(\App\Models\ShopeeStock::getPermissions()['sync'])
            <a href="{{ route('shopee.auto-link.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-orange-200 bg-white px-3 py-1.5 text-sm font-medium text-orange-800 hover:bg-orange-50">
                Auto Link SKUs
            </a>
            @endcan
            <a href="{{ route('shopee.sync.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-orange-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-700">
                Create Mapping
            </a>
        </div>
    </div>

    @php
        $stockConnected = (bool) ($connection['authorized'] ?? false);
        $stockApiReady = (bool) ($connection['ready'] ?? false);
    @endphp
    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800" data-testid="shopee-stock-oauth-panel">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-medium">STOCK CHECKER app (OAuth terpisah dari COREADS / Shopee Ads)</p>
                <p class="mt-2 text-lg font-semibold {{ $stockConnected ? 'text-green-700' : 'text-red-700' }}" data-testid="shopee-stock-oauth-status">
                    {{ $stockConnected ? 'Connected' : 'Not connected' }}
                </p>
                @if($connection['configured'] ?? false)
                    <p class="mt-1 text-xs text-gray-600">
                        Redirect URL terdaftar:
                        <code class="rounded bg-white px-1">{{ $connection['redirect_url'] ?? '—' }}</code>
                        @if($connection['shop_id'] ?? null)
                            · shop_id <span class="font-mono">{{ $connection['shop_id'] }}</span>
                        @endif
                    </p>
                @else
                    <p class="mt-1 text-xs text-amber-800">Set <code>SHOPEE_STOCK_PARTNER_ID</code> / <code>SHOPEE_STOCK_PARTNER_KEY</code> di .env (Live Partner dari app STOCK CHECKER).</p>
                @endif
                @if($stockConnected && ! $stockApiReady)
                    <p class="mt-2 text-xs text-amber-900">
                        Token STOCK CHECKER tersimpan, tapi API belum siap — pastikan <code>SHOPEE_STOCK_PARTNER_ID</code> / <code>SHOPEE_STOCK_PARTNER_KEY</code> benar di .env lalu refresh halaman.
                    </p>
                @endif
                @if(($adsOAuthConnected ?? false) && ! $stockConnected)
                    <p class="mt-2 text-xs text-amber-900">
                        Shopee Ads (COREADS) sudah terhubung, tapi cek stok butuh authorize terpisah di halaman ini — bukan dari menu <strong>Shopee → Ads</strong>.
                    </p>
                @endif
                @if($oauthErrorHint ?? null)
                    <p class="mt-2 text-xs text-red-700">{{ $oauthErrorHint }}</p>
                @elseif($connection['last_error'] ?? null)
                    <p class="mt-2 text-xs text-red-700">{{ $connection['last_error'] }}</p>
                @endif
            </div>
            @can(\App\Models\ShopeeStock::getPermissions()['sync'])
            @if(! $stockConnected)
            <a href="{{ route('shopee.sync.authorize') }}"
               class="inline-flex rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700"
               data-testid="shopee-stock-authorize-button">
                Authorize Shopee (Stock)
            </a>
            @else
            <a href="{{ route('shopee.sync.authorize') }}"
               class="inline-flex rounded-lg border border-orange-300 bg-white px-4 py-2 text-sm font-medium text-orange-800 hover:bg-orange-50"
               data-testid="shopee-stock-reauthorize-button">
                Re-authorize
            </a>
            @endif
            @endcan
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-[10px] font-semibold text-gray-600 uppercase">
                <tr>
                    <th class="px-6 py-4">Shopee warehouse</th>
                    <th class="px-6 py-4">Location ID</th>
                    <th class="px-6 py-4">Aria warehouse</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($dataList as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium">{{ $row->shopee_warehouse_name }}</td>
                    <td class="px-6 py-4 font-mono text-xs">{{ $row->shopee_location_id ?: '—' }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex rounded border border-orange-500/20 px-2 py-0.5 font-medium text-orange-700">{{ $row->warehouse->name ?? 'Unknown' }}</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="{{ route('shopee.sync.edit', $row->id) }}" class="text-orange-600 hover:underline">Edit</a>
                        <form method="POST" action="{{ route('shopee.sync.delete', $row->id) }}" class="ml-3 inline" onsubmit="return confirm('Delete this mapping?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">No mappings yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @include('partials.pagination', ['paginator' => $dataList, 'label' => 'mappings'])
    </div>
</div>
@endsection
