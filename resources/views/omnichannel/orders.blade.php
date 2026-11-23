@extends('layouts.app')

@php
$isEventsView = ($filters['view'] ?? 'events') === 'events';
$pageTitle = $isEventsView ? 'Omnichannel — Webhooks' : 'Omnichannel — Orders';
$crumbLabel = $isEventsView ? 'Webhooks' : 'Orders';
@endphp

@section('title', $pageTitle)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.orders.index', ['view' => $filters['view'] ?? 'events'])],
    ['title' => $crumbLabel, 'href' => route('omnichannel.orders.index', ['view' => $filters['view'] ?? 'events'])],
];
$th = 'px-2 py-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500';
$td = 'px-2 py-2 align-top text-xs text-gray-800';
$channelLabels = ['shopee' => 'Shopee', 'tiktok' => 'TikTok', 'tokopedia' => 'Tokopedia'];
$resetQuery = ['view' => $filters['view'] ?? 'events'];
$currentOrdersUrl = route('omnichannel.orders.index', array_merge(request()->query(), ['view' => 'orders']));
$pageOrderRows = [];
if (! $isEventsView && isset($rows) && $rows !== null) {
    foreach ($rows as $orderRow) {
        $pageOrderRows[] = [
            'channel' => (string) $orderRow->channel,
            'order_ref' => (string) $orderRow->order_ref,
            'shop_ref' => (string) ($orderRow->shop_ref ?? ''),
        ];
    }
}
$hasActiveFilters = collect(request()->query())
    ->only(['channel', 'view', 'date_from', 'date_to', 'order_ref', 'order_status', 'shop_ref', 'warehouse_id', 'per_page'])
    ->filter(fn ($value) => $value !== null && $value !== '')
    ->isNotEmpty();
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4"
     data-testid="omnichannel-orders-page"
     x-data="{
         expandedKey: null,
         expandedId: null,
         detailHtml: {},
         loadingKey: null,
         pageRows: @js($pageOrderRows),
         selected: {},
         rowKey(channel, orderRef) {
             return channel + ':' + orderRef;
         },
         selectionKey(row) {
             return row.channel + ':' + row.order_ref;
         },
         isRowSelected(row) {
             return !!this.selected[this.selectionKey(row)];
         },
         toggleRowSelection(row) {
             const k = this.selectionKey(row);
             if (this.selected[k]) {
                 delete this.selected[k];
             } else {
                 this.selected[k] = row;
             }
         },
         selectedCount() {
             return Object.keys(this.selected).length;
         },
         allPageSelected() {
             if (this.pageRows.length === 0) {
                 return false;
             }
             return this.pageRows.every((row) => this.isRowSelected(row));
         },
         toggleSelectAllPage() {
             if (this.allPageSelected()) {
                 this.pageRows.forEach((row) => delete this.selected[this.selectionKey(row)]);
             } else {
                 this.pageRows.forEach((row) => { this.selected[this.selectionKey(row)] = row; });
             }
         },
         submitAwbPrint(e) {
             e.preventDefault();
             const form = this.$refs.awbForm;
             if (!form) {
                 return;
             }
             form.querySelectorAll('[data-awb-dynamic]').forEach((el) => el.remove());
             const rows = Object.values(this.selected);
             if (rows.length === 0) {
                 alert('Pilih minimal satu baris order.');
                 return;
             }
             rows.forEach((row, index) => {
                 ['channel', 'order_ref', 'shop_ref'].forEach((field) => {
                     const input = document.createElement('input');
                     input.type = 'hidden';
                     input.name = 'rows[' + index + '][' + field + ']';
                     input.value = row[field] ?? '';
                     input.setAttribute('data-awb-dynamic', '1');
                     form.appendChild(input);
                 });
             });
             form.submit();
         },
         async toggleDetail(channel, orderRef, url) {
             const key = this.rowKey(channel, orderRef);
             if (this.expandedKey === key) {
                 this.expandedKey = null;
                 return;
             }
             this.expandedKey = key;
             if (this.detailHtml[key]) {
                 return;
             }
             this.loadingKey = key;
             try {
                 const res = await fetch(url, {
                     headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                 });
                 if (!res.ok) {
                     this.detailHtml[key] = '<p class=\'p-3 text-xs text-red-600\'>Gagal memuat detail.</p>';
                     return;
                 }
                 this.detailHtml[key] = await res.text();
             } catch (e) {
                 this.detailHtml[key] = '<p class=\'p-3 text-xs text-red-600\'>Gagal memuat detail.</p>';
             } finally {
                 this.loadingKey = null;
             }
         },
         isExpanded(channel, orderRef) {
             return this.expandedKey === this.rowKey(channel, orderRef);
         },
         isLoading(channel, orderRef) {
             return this.loadingKey === this.rowKey(channel, orderRef);
         },
         detailFor(channel, orderRef) {
             return this.detailHtml[this.rowKey(channel, orderRef)] ?? '';
         },
         toggleRow(id) {
             this.expandedId = this.expandedId === id ? null : id;
         },
         isRowExpanded(id) {
             return this.expandedId === id;
         }
     }">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $pageTitle }}</h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-600">
            Read-only log dari unified storage
            (<code class="rounded bg-gray-100 px-1 text-xs">marketplace_webhook_events</code>
            / <code class="rounded bg-gray-100 px-1 text-xs">marketplace_channel_orders</code>).
            @if($isEventsView)
            Semua webhook termasuk non-order; tidak mem-post order atau memanggil API marketplace pada render daftar.
            @else
            Ringkasan per order; detail (shadow, webhook, ingress) dimuat lazily — tanpa API Shopee/TikTok/Jubelio pada render awal.
            @endif
        </p>
        @if($isEventsView)
        <span class="mt-3 block max-w-3xl rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900" data-testid="omnichannel-events-current-orders-callout">
            Untuk detail per order (expand baris, shadow compare, dan baris SKU × qty TikTok API), buka
            <a href="{{ $currentOrdersUrl }}" class="font-semibold text-blue-800 underline hover:text-blue-950">Current orders (detail baris &amp; API TikTok)</a>
            — filter saat ini tetap dipakai.
        </span>
        @endif
    </div>

    @include('omnichannel.partials.orders-filters', [
        'filters' => $filters,
        'statusOptions' => $statusOptions,
        'warehouseOptions' => $warehouseOptions,
        'shopOptions' => $shopOptions,
        'resetQuery' => $resetQuery,
        'defaultOpen' => $hasActiveFilters,
    ])

    @if(session('error'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" data-testid="omnichannel-awb-flash-error">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="omnichannel-awb-flash-warning">{{ session('warning') }}</div>
    @endif

    @php
    $missingTable = $isEventsView ? ! $eventsTableExists : ! $ordersTableExists;
    @endphp

    @if($missingTable)
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-sm text-amber-900" data-testid="omnichannel-orders-missing-migration">
        <p class="font-medium">Tabel belum ada</p>
        <p class="mt-1">Jalankan migrasi unified storage:</p>
        <pre class="mt-2 overflow-x-auto rounded-md bg-white/80 px-3 py-2 font-mono text-xs text-gray-800">php artisan migrate --path=database/migrations/2026_11_21_150000_create_marketplace_webhook_events_table.php
php artisan migrate --path=database/migrations/2026_11_21_151000_create_marketplace_channel_orders_table.php</pre>
    </div>
    @elseif($rows === null || $rows->isEmpty())
    <div class="rounded-lg border border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300" data-testid="omnichannel-orders-empty">
        Tidak ada data untuk filter ini.
        @if($isEventsView)
        Aktifkan <code>MARKETPLACE_WEBHOOK_UNIFIED_STORAGE</code> atau jalankan
        <code class="text-xs">php artisan marketplace:backfill-webhook-events</code>.
        @endif
    </div>
    @else
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
        @if(! $isEventsView)
        @php
            require resource_path('views/partials/form-control-class-vars.php');
        @endphp
        <form method="post"
              action="{{ route('omnichannel.orders.shipping-documents') }}"
              x-ref="awbForm"
              class="border-b border-gray-100 bg-gray-50 px-3 py-2"
              data-testid="omnichannel-awb-toolbar">
            @csrf
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <p class="text-xs text-gray-600">
                    <span class="font-medium text-gray-800">Cetak AWB (Shopee)</span>
                    — v1 hanya Shopee Admin Open API. TikTok/Tokopedia belum didukung.
                    <span class="text-gray-500" x-show="selectedCount() > 0" x-cloak>
                        · <span x-text="selectedCount()"></span> terpilih
                    </span>
                </p>
                <button type="button"
                        @click="submitAwbPrint($event)"
                        class="{{ $formControlClassSm }} border-blue-600 bg-blue-600 px-3 font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        data-testid="omnichannel-awb-print-button"
                        :disabled="selectedCount() === 0">
                    Cetak AWB (Shopee)
                </button>
            </div>
        </form>
        @endif
        <div class="border-b border-gray-100 px-3 py-2 text-xs text-gray-500">
            {{ number_format($rows->total()) }} baris — halaman {{ $rows->currentPage() }} / {{ $rows->lastPage() }}
            · {{ $isEventsView ? 'marketplace_webhook_events' : 'marketplace_channel_orders' }}
        </div>
        <div class="min-w-0 overflow-x-auto">
            @if($isEventsView)
            <table class="w-full min-w-[58rem] text-left" data-testid="omnichannel-events-table">
                <thead class="border-b border-gray-200 bg-gray-50">
                    <tr>
                        <th class="{{ $th }} w-14">ID</th>
                        <th class="{{ $th }} w-36">received_at</th>
                        <th class="{{ $th }} w-20">channel</th>
                        <th class="{{ $th }} w-24">platform</th>
                        <th class="{{ $th }} w-16">event</th>
                        <th class="{{ $th }}">order_ref</th>
                        <th class="{{ $th }} w-28">order_status</th>
                        <th class="{{ $th }}">shop_ref</th>
                        <th class="{{ $th }} w-32">gudang</th>
                        <th class="{{ $th }} w-20 text-center">sign OK</th>
                        <th class="{{ $th }} w-24 text-right">payload</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                    @php
                        $gudang = $shopRefToWarehouse[$row->shop_ref ?? ''] ?? null;
                        $gudangName = $gudang ? ($warehouseNames[$gudang] ?? null) : null;
                        $payloadJson = json_encode($row->payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $payloadPreview = \Illuminate\Support\Str::limit($payloadJson, 80, '…');
                        $channelLabel = $channelLabels[$filters['channel']] ?? strtoupper($row->channel);
                        if (strtoupper((string) ($row->commerce_platform ?? '')) === 'TOKOPEDIA') {
                            $channelLabel = 'Tokopedia';
                        } elseif ($row->channel === 'tiktok') {
                            $channelLabel = 'TikTok';
                        } elseif ($row->channel === 'shopee') {
                            $channelLabel = 'Shopee';
                        }
                    @endphp
                    <tr class="hover:bg-gray-50/80">
                        <td class="{{ $td }} font-mono text-[11px]">{{ $row->id }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->received_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="{{ $td }}">{{ $channelLabel }}</td>
                        <td class="{{ $td }} font-mono text-[10px] uppercase">{{ $row->commerce_platform ?? '—' }}</td>
                        <td class="{{ $td }} text-[11px] text-gray-700">{{ $statusPresenter->eventTypeLabel($row->channel, $row->event_type) }}</td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->order_ref ?? '—' }}</td>
                        <td class="{{ $td }}">
                            @php
                                $displayStatus = $statusPresenter->displayStatus($row->channel, $row->order_status);
                                $statusBucket = $statusPresenter->normalizeBucket($row->channel, $row->commerce_platform ?? null, $row->order_status);
                                $bucketLabel = $statusPresenter->bucketChipLabel($statusBucket);
                            @endphp
                            <span class="text-gray-800">{{ $displayStatus }}</span>
                            @if($bucketLabel)
                            <span class="ml-1 inline-flex rounded-full bg-gray-100 px-1.5 py-0.5 text-[9px] font-medium uppercase tracking-wide text-gray-500">{{ $bucketLabel }}</span>
                            @endif
                        </td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->shop_ref ?? '—' }}</td>
                        <td class="{{ $td }} text-gray-600">{{ $gudangName ?? '—' }}</td>
                        <td class="{{ $td }} text-center">
                            @if($row->signature_ok)
                            <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-800">ya</span>
                            @else
                            <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-800">tidak</span>
                            @endif
                        </td>
                        <td class="{{ $td }} text-right">
                            <button type="button"
                                    @click="toggleRow(@js($row->id))"
                                    class="rounded-md border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50"
                                    data-testid="omnichannel-event-expand-{{ $row->id }}"
                                    x-text="isRowExpanded(@js($row->id)) ? 'Tutup' : 'Lihat'">
                                Lihat
                            </button>
                        </td>
                    </tr>
                    <tr x-show="isRowExpanded(@js($row->id))" x-cloak class="bg-gray-50">
                        <td colspan="11" class="px-3 py-3">
                            @if(filled($row->order_ref))
                            @php
                                $eventToOrdersUrl = route('omnichannel.orders.index', array_merge(request()->query(), [
                                    'view' => 'orders',
                                    'order_ref' => $row->order_ref,
                                ]));
                            @endphp
                            <p class="mb-2 text-[11px] text-gray-600" data-testid="omnichannel-event-current-orders-hint">
                                @if($row->channel === 'tiktok')
                                Baris order (SKU × qty) dari TikTok API:
                                @else
                                Detail per order (expand baris):
                                @endif
                                <a href="{{ $eventToOrdersUrl }}" class="font-medium text-blue-700 underline">Current orders</a>
                                @if($row->channel === 'tiktok')
                                untuk order <span class="font-mono">{{ $row->order_ref }}</span>.
                                @else
                                — filter order <span class="font-mono">{{ $row->order_ref }}</span>.
                                @endif
                            </p>
                            @endif
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500">Raw payload (read-only)</p>
                            <p class="mb-2 font-mono text-[11px] text-gray-500 lg:hidden">{{ $payloadPreview }}</p>
                            <pre class="max-h-64 overflow-auto rounded-lg border border-gray-200 bg-white p-3 font-mono text-[11px] leading-relaxed text-gray-800 whitespace-pre-wrap break-all">{{ json_encode($row->payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <table class="w-full min-w-[56rem] text-left" data-testid="omnichannel-orders-table">
                <thead class="border-b border-gray-200 bg-gray-50">
                    <tr>
                        <th class="{{ $th }} w-10 text-center" data-testid="omnichannel-awb-select-all">
                            <input type="checkbox"
                                   class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                   :checked="allPageSelected()"
                                   @change="toggleSelectAllPage()"
                                   aria-label="Pilih semua di halaman ini">
                        </th>
                        <th class="{{ $th }} w-12"></th>
                        <th class="{{ $th }} w-20">channel</th>
                        <th class="{{ $th }} w-24">platform</th>
                        <th class="{{ $th }}">order_ref</th>
                        <th class="{{ $th }} w-28">status</th>
                        <th class="{{ $th }}">shop</th>
                        <th class="{{ $th }} w-32">gudang</th>
                        <th class="{{ $th }} w-36">last_event</th>
                        <th class="{{ $th }} w-36">first_received</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                    @php
                        $gudang = $shopRefToWarehouse[$row->shop_ref ?? ''] ?? null;
                        $gudangName = $gudang ? ($warehouseNames[$gudang] ?? null) : null;
                        $detailUrl = route('omnichannel.orders.show', ['channel' => $row->channel, 'orderRef' => $row->order_ref]);
                        $channelLabel = strtoupper((string) ($row->commerce_platform ?? '')) === 'TOKOPEDIA'
                            ? 'Tokopedia'
                            : ($channelLabels[$row->channel] ?? $row->channel);
                        $awbRow = [
                            'channel' => (string) $row->channel,
                            'order_ref' => (string) $row->order_ref,
                            'shop_ref' => (string) ($row->shop_ref ?? ''),
                        ];
                    @endphp
                    <tr class="hover:bg-gray-50/80">
                        <td class="{{ $td }} text-center">
                            <input type="checkbox"
                                   class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                   data-testid="omnichannel-awb-row-{{ $row->id }}"
                                   :checked="isRowSelected(@js($awbRow))"
                                   @change="toggleRowSelection(@js($awbRow))"
                                   aria-label="Pilih order {{ $row->order_ref }}">
                        </td>
                        <td class="{{ $td }}">
                            <button type="button"
                                    @click="toggleDetail(@js($row->channel), @js($row->order_ref), @js($detailUrl))"
                                    class="rounded-md border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50"
                                    data-testid="omnichannel-order-expand-{{ $row->id }}">
                                <span x-text="isExpanded(@js($row->channel), @js($row->order_ref)) ? 'Tutup' : 'Detail'">Detail</span>
                            </button>
                        </td>
                        <td class="{{ $td }}">{{ $channelLabel }}</td>
                        <td class="{{ $td }} font-mono text-[10px] uppercase">{{ $row->commerce_platform ?? '—' }}</td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->order_ref }}</td>
                        <td class="{{ $td }}">
                            @php
                                $orderDisplayStatus = $statusPresenter->displayStatus($row->channel, $row->order_status);
                                $orderStatusBucket = $statusPresenter->normalizeBucket($row->channel, $row->commerce_platform ?? null, $row->order_status);
                                $orderBucketLabel = $statusPresenter->bucketChipLabel($orderStatusBucket);
                            @endphp
                            <span class="text-gray-800">{{ $orderDisplayStatus }}</span>
                            @if($orderBucketLabel)
                            <span class="ml-1 inline-flex rounded-full bg-gray-100 px-1.5 py-0.5 text-[9px] font-medium uppercase tracking-wide text-gray-500">{{ $orderBucketLabel }}</span>
                            @endif
                        </td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->shop_ref ?? '—' }}</td>
                        <td class="{{ $td }} text-gray-600">{{ $gudangName ?? '—' }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->last_event_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->first_received_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                    </tr>
                    <tr x-show="isExpanded(@js($row->channel), @js($row->order_ref))" x-cloak class="bg-gray-50">
                        <td colspan="10" class="p-0">
                            <template x-if="isLoading(@js($row->channel), @js($row->order_ref))">
                                <p class="p-4 text-xs text-gray-500">Memuat detail…</p>
                            </template>
                            <div x-show="!isLoading(@js($row->channel), @js($row->order_ref))"
                                 x-html="detailFor(@js($row->channel), @js($row->order_ref))"></div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
        @if($rows->hasPages())
        <div class="border-t border-gray-100 px-3 py-3">
            {{ $rows->links() }}
        </div>
        @endif
    </div>
    @endif
</div>
@endsection