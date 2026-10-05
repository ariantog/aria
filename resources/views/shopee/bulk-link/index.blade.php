@extends('layouts.app')

@section('title', 'Shopee Bulk Link')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Shopee Warehouse Mapping', 'href' => route('shopee.sync.index')],
    ['title' => 'Bulk Link', 'href' => route('shopee.bulk-link.index')],
];
$preview = $preview ?? null;
$activeRun = $activeRun ?? null;
$rows = $preview['rows'] ?? [];
$summary = $preview['summary'] ?? null;
$batchSize = \App\Services\Shopee\ShopeeItemBulkLinkService::BATCH_SIZE;
@endphp

@if($activeRun && ($activeRun['status'] ?? '') === 'running')
    @push('head')
    <meta http-equiv="refresh" content="20">
    @endpush
@endif

<div class="flex flex-col gap-4 p-4 sm:p-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Shopee Bulk Link</h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500">
            Upload export Shopee <span class="font-mono">DATA LENGKAP PRODUK</span> (Excel) atau CSV sejenis.
            Apply memproses <strong>{{ number_format($batchSize, 0, ',', '.') }} baris per menit</strong> (batch 1 langsung, sisanya via cron).
            Hanya menulis <span class="font-mono">shopee_item_id</span> / <span class="font-mono">shopee_model_id</span> — tidak mengubah SKU, nama, atau harga.
            Kolom 2 = Kode Variasi, kolom 3 = SKU (<span class="font-mono">legacy_code</span> lalu <span class="font-mono">code</span>).
        </p>
    </div>

    @if($flash['success'] ?? null)
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? null)
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    @if($activeRun)
    <div class="rounded-xl border border-orange-200 bg-white p-5 shadow-sm" data-testid="shopee-bulk-link-run-panel">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Bulk link run #{{ $activeRun['id'] }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $activeRun['filename'] }}</p>
            </div>
            @if(($activeRun['status'] ?? '') === 'running')
            <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold uppercase text-orange-800">Running</span>
            @elseif(($activeRun['status'] ?? '') === 'completed')
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase text-emerald-800">Completed</span>
            @else
            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold uppercase text-red-800">{{ $activeRun['status'] }}</span>
            @endif
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-xs text-gray-600">
                <span>{{ number_format($activeRun['processed_rows'], 0, ',', '.') }} / {{ number_format($activeRun['total_rows'], 0, ',', '.') }} baris</span>
                <span>{{ $activeRun['progress_percent'] }}%</span>
            </div>
            <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-orange-500" style="width: {{ min(100, $activeRun['progress_percent']) }}%"></div>
            </div>
        </div>
        <p class="mt-3 text-sm text-gray-600">
            <span class="text-emerald-700">{{ number_format($activeRun['linked_count'], 0, ',', '.') }} linked</span>,
            {{ number_format($activeRun['skipped_count'], 0, ',', '.') }} skipped,
            <span class="text-red-600">{{ number_format($activeRun['error_count'], 0, ',', '.') }} error</span>
            @if(($activeRun['status'] ?? '') === 'running')
                <span class="text-gray-500">— refresh otomatis; cron <span class="font-mono">app:process-shopee-bulk-link</span> tiap menit.</span>
            @endif
        </p>
        @if(! empty($activeRun['rows']))
            @include('shopee.bulk-link.partials.result-table', ['rows' => $activeRun['rows']])
            @if($activeRun['rows_truncated'] ?? false)
            <p class="mt-2 text-xs text-gray-500">Menampilkan batch terakhir (max {{ count($activeRun['rows']) }} baris).</p>
            @endif
        @endif
    </div>
    @endif

    @if(! ($stockReady ?? false))
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        STOCK CHECKER belum siap — apply butuh OAuth di
        <a href="{{ route('shopee.sync.index') }}" class="font-medium underline">Warehouse Map</a>.
        Preview tetap bisa tanpa OAuth.
    </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-900">Format file</h2>
            <p class="mt-2 text-sm text-gray-600">Baris pertama = header Shopee. Maks. {{ number_format(\App\Services\Shopee\ShopeeItemBulkLinkParser::MAX_ROWS, 0, ',', '.') }} baris.</p>
            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-100 bg-gray-50">
                <table class="min-w-full text-left text-xs">
                    <thead class="border-b border-gray-200 text-gray-500">
                        <tr>
                            <th class="px-3 py-2 font-medium">Kode Produk</th>
                            <th class="px-3 py-2 font-medium">Kode Variasi</th>
                            <th class="px-3 py-2 font-medium">SKU</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-gray-800">
                        <tr class="border-b border-gray-100"><td class="px-3 py-2 text-gray-400">58000473010</td><td class="px-3 py-2">395043520573</td><td class="px-3 py-2">ELBOWSUPPORT-05-BLUE</td></tr>
                        <tr><td class="px-3 py-2 text-gray-400">57918450371</td><td class="px-3 py-2">391549925714</td><td class="px-3 py-2">DUMBBELL-07-BLACK-32KG</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-900">Upload</h2>
            @if($activeRun && ($activeRun['status'] ?? '') === 'running')
            <p class="mt-2 text-sm text-amber-800">Tunggu run aktif selesai sebelum upload file baru.</p>
            @else
            <form method="POST" action="{{ route('shopee.bulk-link.preview') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="bulk-link-file" class="block text-xs font-medium text-gray-700">File (.xlsx, .xls, .csv)</label>
                    <input id="bulk-link-file" name="file" type="file" accept=".xlsx,.xls,.csv,.txt" required
                           class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-orange-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-orange-700 hover:file:bg-orange-100">
                </div>
                <button type="submit" data-testid="shopee-bulk-link-preview"
                        class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                    Preview
                </button>
            </form>
            @endif
        </div>
    </div>

    @if($preview && ! ($activeRun && ($activeRun['status'] ?? '') === 'running'))
    <div class="rounded-xl border border-blue-200 bg-white p-5 shadow-sm" data-testid="shopee-bulk-link-preview-panel">
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
            <form method="POST" action="{{ route('shopee.bulk-link.apply') }}" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="hidden" name="token" value="{{ $preview['token'] ?? '' }}">
                <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input type="checkbox" name="overwrite_existing" value="1" class="rounded border-gray-300">
                    Overwrite SKU yang sudah punya Shopee ID
                </label>
                <button type="submit" data-testid="shopee-bulk-link-apply"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Start bulk link
                </button>
            </form>
        </div>

        @include('shopee.bulk-link.partials.result-table', ['rows' => $rows])
    </div>
    @endif
</div>
@endsection
