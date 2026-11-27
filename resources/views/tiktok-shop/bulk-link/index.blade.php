@extends('layouts.app')

@section('title', 'TikTok Shop Bulk Link')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.index')],
    ['title' => 'Bulk Link', 'href' => route('tiktok-shop.bulk-link.index')],
];
$preview = $preview ?? null;
$displayRun = $displayRun ?? null;
$bulkLinkRunning = $bulkLinkRunning ?? false;
$rows = $preview['rows'] ?? [];
$summary = $preview['summary'] ?? null;
$batchSize = \App\Services\TikTokShop\TikTokShopItemBulkLinkService::BATCH_SIZE;
@endphp

@if($displayRun && ($displayRun['status'] ?? '') === 'running')
    @push('head')
    <meta http-equiv="refresh" content="20">
    @endpush
@endif

<div class="flex flex-col gap-4 p-4 sm:p-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">TikTok Shop Bulk Link</h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500">
            Upload export produk TikTok Shop (Excel/CSV) atau template sederhana.
            Apply memproses <strong>{{ number_format($batchSize, 0, ',', '.') }} baris per menit</strong> (batch 1 langsung, sisanya via cron).
            Hanya menulis <span class="font-mono">tiktok_shop_product_id</span> / <span class="font-mono">tiktok_shop_sku_id</span> — tidak mengubah SKU, nama, atau harga.
            Match SKU Aria: <span class="font-mono">legacy_code</span> lalu <span class="font-mono">code</span>, atau <span class="font-mono">aria_item_id</span>.
        </p>
    </div>

    @if($flash['success'] ?? null)
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? null)
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    @if($displayRun)
    <div class="rounded-xl border border-gray-300 bg-white p-5 shadow-sm" data-testid="tiktok-bulk-link-run-panel">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Bulk link run #{{ $displayRun['id'] }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $displayRun['filename'] }}</p>
            </div>
            @if(($displayRun['status'] ?? '') === 'running')
            <span class="rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold uppercase text-gray-800">Running</span>
            @elseif(($displayRun['status'] ?? '') === 'completed')
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase text-emerald-800">Completed</span>
            @else
            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold uppercase text-red-800">{{ $displayRun['status'] }}</span>
            @endif
        </div>
        @if(! empty($displayRun['error_message']))
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" data-testid="tiktok-bulk-link-run-error">
            {{ $displayRun['error_message'] }}
        </div>
        @endif
        <div class="mt-4">
            <div class="flex justify-between text-xs text-gray-600">
                <span>{{ number_format($displayRun['processed_rows'], 0, ',', '.') }} / {{ number_format($displayRun['total_rows'], 0, ',', '.') }} baris</span>
                <span>{{ $displayRun['progress_percent'] }}%</span>
            </div>
            <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-gray-800" style="width: {{ min(100, $displayRun['progress_percent']) }}%"></div>
            </div>
        </div>
        <p class="mt-3 text-sm text-gray-600">
            <span class="text-emerald-700">{{ number_format($displayRun['linked_count'], 0, ',', '.') }} linked</span>,
            {{ number_format($displayRun['skipped_count'], 0, ',', '.') }} skipped,
            <span class="text-red-600">{{ number_format($displayRun['error_count'], 0, ',', '.') }} error</span>
            @if(($displayRun['status'] ?? '') === 'running')
                <span class="text-gray-500">— refresh otomatis; cron <span class="font-mono">app:process-tiktok-bulk-link</span> tiap menit.</span>
            @endif
        </p>
        @if(! empty($displayRun['failed_rows']))
        <div class="mt-5 border-t border-gray-100 pt-4" data-testid="tiktok-bulk-link-failed-rows">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-red-800">
                Baris gagal link ({{ count($displayRun['failed_rows']) }}
                @if($displayRun['failed_rows_truncated'] ?? false)
                    / {{ number_format($displayRun['error_count'], 0, ',', '.') }} total
                @endif
                )
            </h3>
            <p class="mt-1 text-xs text-gray-500">Disimpan dari setiap batch cron — tetap tampil setelah run selesai.</p>
            @include('tiktok-shop.bulk-link.partials.result-table', ['rows' => $displayRun['failed_rows'], 'itemModels' => $itemModels ?? collect()])
            @if($displayRun['failed_rows_truncated'] ?? false)
            <p class="mt-2 text-xs text-amber-800">Hanya {{ number_format(count($displayRun['failed_rows']), 0, ',', '.') }} error terakhir disimpan (batas {{ number_format(\App\Services\TikTokShop\TikTokShopItemBulkLinkService::FAILED_RESULTS_LIMIT, 0, ',', '.') }}).</p>
            @endif
        </div>
        @endif
        @if(($displayRun['error_count'] ?? 0) === 0 && ($displayRun['status'] ?? '') === 'completed')
        <p class="mt-3 text-sm text-emerald-700" data-testid="tiktok-bulk-link-no-errors">Tidak ada baris gagal — semua sudah ter-link atau dilewati.</p>
        @endif
    </div>
    @endif

    @if(! ($oauthReady ?? false))
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        OAuth TikTok Shop belum siap — bulk link apply tetap jalan (hanya menulis ID dari file, tanpa verifikasi API).
        <a href="{{ route('omnichannel.settings.index', ['tab' => 'connections']) }}" class="font-medium underline">Omnichannel → Settings</a>.
    </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-900">Format file</h2>
            <p class="mt-2 text-sm text-gray-600">Baris pertama = header. Maks. {{ number_format(\App\Services\TikTokShop\TikTokShopItemBulkLinkParser::MAX_ROWS, 0, ',', '.') }} baris.</p>
            <p class="mt-2 text-sm text-gray-600">
                <strong>Export TikTok Shop</strong> (deteksi otomatis): <span class="font-mono">product_id</span>, <span class="font-mono">sku_id</span>, <span class="font-mono">seller_sku</span>.
            </p>
            <p class="mt-1 text-sm text-gray-600">
                <strong>Template sederhana</strong>: kolom bebas dengan header seperti <span class="font-mono">code</span> / <span class="font-mono">seller_sku</span>, <span class="font-mono">product_id</span> / <span class="font-mono">tiktok_shop_product_id</span>, <span class="font-mono">sku_id</span> / <span class="font-mono">tiktok_shop_sku_id</span>, opsional <span class="font-mono">aria_item_id</span>.
            </p>
            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-100 bg-gray-50">
                <table class="min-w-full text-left text-xs">
                    <thead class="border-b border-gray-200 text-gray-500">
                        <tr>
                            <th class="px-3 py-2 font-medium">product_id</th>
                            <th class="px-3 py-2 font-medium">sku_id</th>
                            <th class="px-3 py-2 font-medium">seller_sku</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-gray-800">
                        <tr class="border-b border-gray-100"><td class="px-3 py-2">7490000000000000101</td><td class="px-3 py-2">7491000000000000101</td><td class="px-3 py-2">AJD-CX90324-05-S</td></tr>
                        <tr><td class="px-3 py-2">7490000000000000202</td><td class="px-3 py-2">7491000000000000202</td><td class="px-3 py-2">GLOVE-01-BLACK-S</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-900">Upload</h2>
            @if($bulkLinkRunning)
            <p class="mt-2 text-sm text-amber-800">Tunggu run aktif selesai sebelum upload file baru.</p>
            @else
            <form method="POST" action="{{ route('tiktok-shop.bulk-link.preview') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="bulk-link-file" class="block text-xs font-medium text-gray-700">File (.xlsx, .xls, .csv)</label>
                    <input id="bulk-link-file" name="file" type="file" accept=".xlsx,.xls,.csv,.txt" required
                           class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-800 hover:file:bg-gray-200">
                </div>
                <button type="submit" data-testid="tiktok-bulk-link-preview"
                        class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                    Preview
                </button>
            </form>
            @endif
        </div>
    </div>

    @if($preview && ! $bulkLinkRunning)
    <div class="rounded-xl border border-blue-200 bg-white p-5 shadow-sm" data-testid="tiktok-bulk-link-preview-panel">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Preview</h2>
                @if($summary)
                <p class="mt-1 text-sm text-gray-600">
                    {{ $preview['rows_total'] ?? $summary['total'] }} baris —
                    <span class="text-emerald-700">{{ $summary['ready'] ?? 0 }} siap</span>,
                    {{ $summary['skipped'] }} skipped,
                    <span class="text-red-600">{{ $summary['errors'] }} error</span>
                </p>
                @endif
            </div>
            <form method="POST" action="{{ route('tiktok-shop.bulk-link.apply') }}" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="hidden" name="token" value="{{ $preview['token'] ?? '' }}">
                <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input type="checkbox" name="overwrite_existing" value="1" class="rounded border-gray-300">
                    Overwrite SKU yang sudah punya TikTok ID
                </label>
                <button type="submit" data-testid="tiktok-bulk-link-apply"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Start bulk link
                </button>
            </form>
        </div>

        @include('tiktok-shop.bulk-link.partials.result-table', ['rows' => $rows, 'itemModels' => $itemModels ?? collect()])
    </div>
    @endif
</div>
@endsection