@extends('layouts.app')

@section('title', 'Jubelio Orders')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio Orders', 'href' => route('jubelio.index')],
];
$th = 'px-1.5 py-2 text-[10px] font-semibold tracking-wide text-gray-500 uppercase';
$td = 'px-1.5 py-2 align-top';
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4">
    {{-- Header --}}
    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
        <h1 class="flex items-center gap-2 text-2xl font-bold">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 7l-1 11a1 1 0 01-1 1H8a1 1 0 01-1-1L6 7m3-3h6m-9 3h12"/></svg>
            Jubelio Orders
        </h1>

        <form method="GET" action="{{ route('jubelio.index') }}" class="flex w-full flex-wrap items-end gap-2 md:w-auto">
            <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
            <label class="flex min-w-[10rem] flex-1 flex-col gap-0.5 md:flex-none">
                <span class="text-[10px] font-semibold uppercase text-gray-500">Gudang</span>
                <select name="warehouse_id"
                        onchange="this.form.submit()"
                        class="h-9 w-full rounded-md border border-gray-300 bg-white px-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 md:min-w-[12rem]"
                        data-testid="jubelio-warehouse-filter">
                    <option value="">Semua gudang</option>
                    @foreach($mappedWarehouses as $warehouse)
                    <option value="{{ $warehouse['id'] }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse['id'])>{{ $warehouse['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="relative flex min-w-[12rem] flex-1 flex-col gap-0.5 md:flex-none">
                <span class="text-[10px] font-semibold uppercase text-gray-500">Invoice</span>
                <span class="relative">
                    <svg class="absolute top-2.5 left-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input type="text" name="invoice" value="{{ $filters['invoice'] ?? '' }}" placeholder="Search Invoice..."
                           class="h-9 w-full rounded-md border border-gray-300 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 md:w-56">
                </span>
            </label>
            <button type="submit" class="h-9 rounded-lg bg-blue-700 px-3 text-sm font-medium text-white hover:bg-blue-800">Search</button>
            @if(!empty($filters['status']) || !empty($filters['invoice']) || !empty($filters['warehouse_id']))
            <a href="{{ route('jubelio.index') }}" class="inline-flex h-9 items-center gap-1 rounded-lg px-3 text-sm font-medium text-gray-600 hover:bg-gray-100">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Clear
            </a>
            @endif
        </form>
    </div>

    @if(!empty($flash['success']))
    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ $flash['success'] }}</div>
    @endif
    @if(!empty($flash['error']))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
        @php
        $cards = [
            ['status' => 'pending', 'title' => 'Pending',    'value' => $stats['pending'], 'border' => 'border-blue-500',   'bg' => 'bg-blue-50 text-blue-600',     'ring' => 'ring-2 ring-blue-500'],
            ['status' => 'success', 'title' => 'Success',    'value' => $stats['success'], 'border' => 'border-green-500',  'bg' => 'bg-green-50 text-green-600',   'ring' => 'ring-2 ring-green-500'],
            ['status' => 'warning', 'title' => 'Duplicate',  'value' => $stats['warning'], 'border' => 'border-yellow-500', 'bg' => 'bg-yellow-50 text-yellow-600', 'ring' => 'ring-2 ring-yellow-500'],
            ['status' => 'error',   'title' => 'Error SKU',  'value' => $stats['error'],   'border' => 'border-red-500',    'bg' => 'bg-red-50 text-red-600',       'ring' => 'ring-2 ring-red-500'],
        ];
        $activeStatus = $filters['status'] ?? '';
        @endphp
        @foreach($cards as $c)
        @php $isActive = ($c['status'] === 'pending' && $activeStatus === '') || $activeStatus === $c['status']; @endphp
        <a href="{{ route('jubelio.index', ['status' => $c['status'], 'invoice' => $filters['invoice'] ?? null, 'warehouse_id' => $filters['warehouse_id'] ?? null]) }}"
           class="flex items-center justify-between rounded-lg border-l-4 {{ $c['border'] }} {{ $c['bg'] }} p-3 shadow-sm transition-all hover:opacity-90 md:p-4 {{ $isActive ? $c['ring'] : '' }}">
            <div>
                <p class="text-[10px] font-semibold tracking-wider uppercase opacity-70 md:text-xs">{{ $c['title'] }}</p>
                <p class="text-xl font-bold md:text-2xl">{{ format_amount($c['value'], 0) }}</p>
            </div>
        </a>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="min-w-0">
            <table class="w-full table-fixed text-left text-xs" data-testid="jubelio-orders-table">
                <colgroup>
                    <col class="w-[12%]">
                    <col class="w-[16%]">
                    <col class="w-[13%]">
                    <col class="w-[17%]">
                    <col class="w-[7%]">
                    <col class="w-[5%]">
                    <col class="w-[10%]">
                    <col class="w-[20%]">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="{{ $th }}">Date</th>
                        <th class="{{ $th }}">Invoice</th>
                        <th class="{{ $th }}">Store</th>
                        <th class="{{ $th }}">Warehouse</th>
                        <th class="{{ $th }} text-center">Type</th>
                        <th class="{{ $th }} text-center">Qty</th>
                        <th class="{{ $th }} text-right">Total</th>
                        <th class="{{ $th }}">Sync Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($orders as $order)
                    @php
                        $summary = $order->payloadSummary();
                        $payloadDate = $summary['transaction_date'];
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="{{ $td }}">
                            <div class="flex flex-col leading-tight">
                                <span class="text-[10px] font-bold text-gray-800">{{ \Carbon\Carbon::parse($order->updated_at)->format('d/m H:i') }}</span>
                                @if($payloadDate)
                                <span class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($payloadDate)->format('d/m H:i') }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="{{ $td }}">
                            <a href="{{ route('jubelio.show', $order->id) }}" class="break-all font-medium text-blue-600 hover:underline">{{ $order->invoice }}</a>
                            <div class="mt-0.5 text-[10px] text-gray-400">{{ $order->order_status }}</div>
                            <a href="{{ $order->transactionsSearchUrl() }}"
                               class="mt-0.5 inline-flex items-center gap-0.5 text-[10px] font-medium text-gray-500 hover:text-blue-600"
                               title="Cari transaksi Aria dengan invoice ini">
                                Cek transaksi
                            </a>
                        </td>
                        <td class="{{ $td }}">
                            <div class="break-words font-medium text-gray-800">{{ $summary['store_name'] ?: '—' }}</div>
                            @if($summary['customer_name'])
                            <div class="mt-0.5 break-words text-[10px] text-gray-500">{{ $summary['customer_name'] }}</div>
                            @endif
                        </td>
                        <td class="{{ $td }}">
                            <div class="space-y-0.5 break-words">
                                <div class="text-gray-800">
                                    <span class="text-[9px] font-bold uppercase text-gray-400">J</span>
                                    {{ $order->jubelio_warehouse ?: ($summary['location_name'] ?: '—') }}
                                </div>
                                <div>
                                    <span class="text-[9px] font-bold uppercase text-gray-400">A</span>
                                    @if($order->aria_warehouse_url)
                                    <a href="{{ $order->aria_warehouse_url }}"
                                       class="font-medium text-blue-600 hover:underline"
                                       data-testid="jubelio-aria-warehouse-link">{{ $order->aria_warehouse }}</a>
                                    @else
                                    <span class="font-medium text-gray-700">{{ $order->aria_warehouse ?: '—' }}</span>
                                    @if(!$order->aria_warehouse)
                                    <div class="mt-0.5 text-[10px] leading-tight text-amber-700" title="Cek mapping di Jubelio Sync">
                                        @if(($order->payload_store_id ?? 0) > 0 && ($order->payload_location_id ?? 0) > 0)
                                            store {{ $order->payload_store_id }} / loc {{ $order->payload_location_id }} — belum di-sync
                                        @else
                                            store/loc kosong di payload Jubelio
                                        @endif
                                    </div>
                                    @endif
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="{{ $td }} text-center">
                            <span class="rounded bg-gray-100 px-1 py-px text-[9px] font-bold uppercase leading-none">{{ $order->type }}</span>
                        </td>
                        <td class="{{ $td }} text-center font-mono text-[11px] text-gray-700">
                            {{ $summary['item_count'] > 0 ? format_amount($summary['item_count'], 0) : '—' }}
                        </td>
                        <td class="{{ $td }} text-right font-mono text-[11px] text-gray-800">
                            @if($summary['real_total'] !== null)
                                {{ format_amount($summary['real_total']) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="{{ $td }}" data-testid="jubelio-orders-sync-status">
                            <div class="flex min-w-0 flex-col items-start gap-0.5">
                                @include('jubelio.partials.sync-status-badge', ['status' => $order->status, 'errorType' => $order->error_type, 'executeBy' => $order->user->name ?? null])
                                <form method="POST" action="{{ route('jubelio.refresh-payload', $order) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="text-[10px] font-medium text-gray-500 hover:text-blue-600"
                                            title="Ambil ulang payload dari Jubelio API dan perbarui mapping gudang"
                                            data-testid="jubelio-refresh-payload-{{ $order->id }}">
                                        ↻ Refresh
                                    </button>
                                </form>
                                @if($order->hasStockError())
                                @include('jubelio.partials.stock-error-items', ['stockErrorItems' => $order->stockErrorItemsList()])
                                @elseif(((($order->status == 1 && $order->error_type == 1)) || ($order->status == 2 && $order->error_type == 2)) && $order->error)
                                <p class="max-w-full truncate text-[10px] text-red-600" title="{{ $order->error }}">{{ $order->error }}</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-3 py-8 text-center text-gray-400 italic">No Jubelio orders found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $orders, 'label' => 'orders'])
    </div>
</div>
@endsection
