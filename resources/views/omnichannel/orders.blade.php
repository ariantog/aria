@extends('layouts.app')

@section('title', 'Omnichannel — Orders')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.orders.index')],
    ['title' => 'Orders', 'href' => route('omnichannel.orders.index')],
];
$th = 'px-2 py-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500';
$td = 'px-2 py-2 align-top text-xs text-gray-800';
$channelLabels = ['shopee' => 'Shopee', 'tiktok' => 'TikTok'];
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4"
     data-testid="omnichannel-orders-page"
     x-data="{
         expandedKey: null,
         detailHtml: {},
         loadingKey: null,
         rowKey(channel, orderRef) {
             return channel + ':' + orderRef;
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
         }
     }">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Omnichannel — Orders</h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-600">
            Read-only daftar order dari <code class="rounded bg-gray-100 px-1 text-xs">marketplace_channel_orders</code>.
            Detail (shadow, webhook, ingress) dimuat lazily — tanpa API Shopee/TikTok/Jubelio pada render awal.
        </p>
    </div>

    <form method="get" action="{{ route('omnichannel.orders.index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="omnichannel-orders-filters">
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
                <label for="date_from" class="mb-1 block text-xs font-medium text-gray-600">Dari</label>
                <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from']->format('Y-m-d') }}" class="w-full rounded-md border-gray-300 text-sm">
            </div>
            <div>
                <label for="date_to" class="mb-1 block text-xs font-medium text-gray-600">Sampai</label>
                <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to']->format('Y-m-d') }}" class="w-full rounded-md border-gray-300 text-sm">
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
                <label for="warehouse_id" class="mb-1 block text-xs font-medium text-gray-600">Gudang</label>
                <select name="warehouse_id" id="warehouse_id" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">Semua</option>
                    @foreach($warehouseOptions as $wid => $wname)
                    <option value="{{ $wid }}" @selected((int) $filters['warehouse_id'] === (int) $wid)>{{ $wname }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Terapkan</button>
                <a href="{{ route('omnichannel.orders.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </div>
    </form>

    @if(! $ordersTableExists)
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-sm text-amber-900" data-testid="omnichannel-orders-missing-migration">
        <p class="font-medium">Tabel belum ada</p>
        <p class="mt-1">Jalankan migrasi unified storage:</p>
        <pre class="mt-2 overflow-x-auto rounded-md bg-white/80 px-3 py-2 font-mono text-xs text-gray-800">php artisan migrate --path=database/migrations/2026_11_21_151000_create_marketplace_channel_orders_table.php</pre>
    </div>
    @elseif($rows === null || $rows->isEmpty())
    <div class="rounded-lg border border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-600 shadow-sm" data-testid="omnichannel-orders-empty">
        Tidak ada order untuk filter ini.
    </div>
    @else
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-3 py-2 text-xs text-gray-500">
            {{ number_format($rows->total()) }} order — halaman {{ $rows->currentPage() }} / {{ $rows->lastPage() }}
        </div>
        <div class="min-w-0 overflow-x-auto">
            <table class="w-full min-w-[52rem] text-left" data-testid="omnichannel-orders-table">
                <thead class="border-b border-gray-200 bg-gray-50">
                    <tr>
                        <th class="{{ $th }} w-12"></th>
                        <th class="{{ $th }} w-20">channel</th>
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
                    @endphp
                    <tr class="hover:bg-gray-50/80">
                        <td class="{{ $td }}">
                            <button type="button"
                                    @click="toggleDetail(@js($row->channel), @js($row->order_ref), @js($detailUrl))"
                                    class="rounded-md border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50"
                                    data-testid="omnichannel-order-expand-{{ $row->id }}">
                                <span x-text="isExpanded(@js($row->channel), @js($row->order_ref)) ? 'Tutup' : 'Detail'">Detail</span>
                            </button>
                        </td>
                        <td class="{{ $td }} uppercase">{{ $channelLabels[$row->channel] ?? $row->channel }}</td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->order_ref }}</td>
                        <td class="{{ $td }}">{{ $row->order_status ?? '—' }}</td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->shop_ref ?? '—' }}</td>
                        <td class="{{ $td }} text-gray-600">{{ $gudangName ?? '—' }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->last_event_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->first_received_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                    </tr>
                    <tr x-show="isExpanded(@js($row->channel), @js($row->order_ref))" x-cloak class="bg-gray-50">
                        <td colspan="8" class="p-0">
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