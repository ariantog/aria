@extends('layouts.app')

@section('title', 'Marketplace Webhook Inbox')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Webhook Inbox', 'href' => route('marketplace.webhooks.index')],
];
$th = 'px-2 py-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500';
$td = 'px-2 py-2 align-top text-xs text-gray-800';
$channelLabels = ['shopee' => 'Shopee', 'tiktok' => 'TikTok'];
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4"
     data-testid="marketplace-webhooks-page"
     x-data="{
         expandedId: null,
         toggleRow(id) {
             this.expandedId = this.expandedId === id ? null : id;
         },
         isExpanded(id) {
             return this.expandedId === id;
         }
     }">
    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Marketplace — Webhook Inbox</h1>
            <p class="mt-1 max-w-3xl text-sm text-gray-600">
                Read-only log from unified storage (<code class="rounded bg-gray-100 px-1 text-xs">marketplace_webhook_events</code>
                / <code class="rounded bg-gray-100 px-1 text-xs">marketplace_channel_orders</code>).
                Tidak mem-post order atau memanggil API marketplace.
            </p>
        </div>
    </div>

    <form method="get" action="{{ route('marketplace.webhooks.index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="marketplace-webhooks-filters">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="channel" class="mb-1 block text-xs font-medium text-gray-600">Marketplace</label>
                <select name="channel" id="channel" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="all" @selected($filters['channel'] === 'all')>Semua</option>
                    <option value="shopee" @selected($filters['channel'] === 'shopee')>Shopee</option>
                    <option value="tiktok" @selected($filters['channel'] === 'tiktok')>TikTok</option>
                </select>
            </div>
            <div>
                <label for="view" class="mb-1 block text-xs font-medium text-gray-600">Tampilan</label>
                <select name="view" id="view" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="events" @selected($filters['view'] === 'events')>Events (history)</option>
                    <option value="orders" @selected($filters['view'] === 'orders')>Current orders</option>
                </select>
            </div>
            <div>
                <label for="date_from" class="mb-1 block text-xs font-medium text-gray-600">Dari</label>
                <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from']->format('Y-m-d') }}" class="w-full rounded-md border-gray-300 text-sm">
            </div>
            <div>
                <label for="date_to" class="mb-1 block text-xs font-medium text-gray-600">Sampai</label>
                <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to']->format('Y-m-d') }}" class="w-full rounded-md border-gray-300 text-sm">
            </div>
            <div>
                <label for="order_ref" class="mb-1 block text-xs font-medium text-gray-600">Order ref</label>
                <input type="text" name="order_ref" id="order_ref" value="{{ $filters['order_ref'] ?? '' }}" placeholder="Cari nomor order…" autocomplete="off" class="w-full rounded-md border-gray-300 text-sm font-mono text-xs">
                <p class="mt-1 text-[10px] text-gray-500">Partial match; mengisi ini mengabaikan filter tanggal.</p>
            </div>
            <div>
                <label for="order_status" class="mb-1 block text-xs font-medium text-gray-600">Status order</label>
                <input type="text" name="order_status" id="order_status" list="order-status-options" value="{{ $filters['order_status'] ?? '' }}" placeholder="Partial match…" class="w-full rounded-md border-gray-300 text-sm">
                <datalist id="order-status-options">
                    @foreach($statusOptions as $status)
                    <option value="{{ $status }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div>
                <label for="shop_ref" class="mb-1 block text-xs font-medium text-gray-600">Shop / store</label>
                <select name="shop_ref" id="shop_ref" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">Semua</option>
                    @foreach($shopOptions as $shop)
                    <option value="{{ $shop }}" @selected($filters['shop_ref'] === $shop)>{{ $shop }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="warehouse_id" class="mb-1 block text-xs font-medium text-gray-600">Gudang (Shopee map)</label>
                <select name="warehouse_id" id="warehouse_id" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">Semua</option>
                    @foreach($warehouseOptions as $wid => $wname)
                    <option value="{{ $wid }}" @selected((int) $filters['warehouse_id'] === (int) $wid)>{{ $wname }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[10px] text-gray-500">Filter via <code>shopee_syncs.shop_id</code> → <code>shop_ref</code>.</p>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Terapkan</button>
                <a href="{{ route('marketplace.webhooks.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </div>
    </form>

    @if(!$eventsTableExists && !$ordersTableExists)
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950" data-testid="marketplace-webhooks-missing-table">
        <p class="font-semibold">Tabel belum tersedia</p>
        <p class="mt-1">Jalankan migrasi unified storage (PR1) lalu aktifkan dual-write atau jalankan backfill.</p>
        <pre class="mt-2 overflow-x-auto rounded-md bg-white/80 px-3 py-2 font-mono text-xs text-gray-800">php artisan migrate --path=database/migrations/2026_11_21_150000_create_marketplace_webhook_events_table.php
php artisan migrate --path=database/migrations/2026_11_21_151000_create_marketplace_channel_orders_table.php</pre>
    </div>
    @elseif($rows === null || $rows->isEmpty())
    <div class="rounded-lg border border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-600 shadow-sm" data-testid="marketplace-webhooks-empty">
        Tidak ada data untuk filter ini. Aktifkan <code>MARKETPLACE_WEBHOOK_UNIFIED_STORAGE</code> atau jalankan
        <code class="text-xs">php artisan marketplace:backfill-webhook-events</code>.
    </div>
    @else
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-3 py-2 text-xs text-gray-500">
            {{ number_format($rows->total()) }} baris — halaman {{ $rows->currentPage() }} / {{ $rows->lastPage() }}
            · {{ $filters['view'] === 'orders' ? 'marketplace_channel_orders' : 'marketplace_webhook_events' }}
        </div>
        <div class="min-w-0 overflow-x-auto">
            @if($filters['view'] === 'orders')
            <table class="w-full min-w-[52rem] text-left" data-testid="marketplace-webhooks-orders-table">
                <thead class="border-b border-gray-200 bg-gray-50">
                    <tr>
                        <th class="{{ $th }} w-14">ID</th>
                        <th class="{{ $th }} w-20">channel</th>
                        <th class="{{ $th }}">order_ref</th>
                        <th class="{{ $th }} w-28">order_status</th>
                        <th class="{{ $th }}">shop_ref</th>
                        <th class="{{ $th }} w-32">gudang</th>
                        <th class="{{ $th }} w-36">last_event_at</th>
                        <th class="{{ $th }} w-36">first_received</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                    @php
                        $gudang = $shopRefToWarehouse[$row->shop_ref ?? ''] ?? null;
                        $gudangName = $gudang ? ($warehouseNames[$gudang] ?? null) : null;
                    @endphp
                    <tr class="hover:bg-gray-50/80">
                        <td class="{{ $td }} font-mono text-[11px]">{{ $row->id }}</td>
                        <td class="{{ $td }} uppercase">{{ $channelLabels[$row->channel] ?? $row->channel }}</td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->order_ref }}</td>
                        <td class="{{ $td }}">{{ $row->order_status ?? '—' }}</td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->shop_ref ?? '—' }}</td>
                        <td class="{{ $td }} text-gray-600">{{ $gudangName ?? '—' }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->last_event_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->first_received_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <table class="w-full min-w-[56rem] text-left" data-testid="marketplace-webhooks-events-table">
                <thead class="border-b border-gray-200 bg-gray-50">
                    <tr>
                        <th class="{{ $th }} w-14">ID</th>
                        <th class="{{ $th }} w-36">received_at</th>
                        <th class="{{ $th }} w-20">channel</th>
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
                    @endphp
                    <tr class="hover:bg-gray-50/80">
                        <td class="{{ $td }} font-mono text-[11px]">{{ $row->id }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->received_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="{{ $td }} uppercase">{{ $channelLabels[$row->channel] ?? $row->channel }}</td>
                        <td class="{{ $td }} font-mono">{{ $row->event_type ?? '—' }}</td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->order_ref ?? '—' }}</td>
                        <td class="{{ $td }}">{{ $row->order_status ?? '—' }}</td>
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
                                    data-testid="marketplace-webhook-expand-{{ $row->id }}"
                                    x-text="isExpanded(@js($row->id)) ? 'Tutup' : 'Lihat'">
                                Lihat
                            </button>
                        </td>
                    </tr>
                    <tr x-show="isExpanded(@js($row->id))" x-cloak class="bg-gray-50">
                        <td colspan="10" class="px-3 py-3">
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500">Raw payload (read-only)</p>
                            <p class="mb-2 font-mono text-[11px] text-gray-500 lg:hidden">{{ $payloadPreview }}</p>
                            <pre class="max-h-64 overflow-auto rounded-lg border border-gray-200 bg-white p-3 font-mono text-[11px] leading-relaxed text-gray-800 whitespace-pre-wrap break-all">{{ json_encode($row->payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
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