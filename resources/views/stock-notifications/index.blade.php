@extends('layouts.app')

@section('title', 'Stock Alerts')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Stock Alerts', 'href' => route('stock-notifications.index')],
];
$fmtNum = fn ($v) => format_amount($v, 0);
@endphp

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Stock Alerts</h1>
            <p class="text-gray-500">
                Items sold out at arrangement-enabled shops while stock remains at another warehouse — available, slow moving, or dead stock.
                Only warehouses with <span class="font-medium">Warehouse Arrangement</span> enabled on the addrbook form trigger alerts.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($unreadCount > 0)
            <form method="POST" action="{{ route('stock-notifications.mark-all-read') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Mark all read
                </button>
            </form>
            @endif
            <a href="{{ route('reports.warehouse-arrangement') }}"
               class="rounded-lg bg-blue-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-800">
                Warehouse Arrangement
            </a>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 text-sm">
        <a href="{{ route('stock-notifications.index') }}"
           class="rounded-md px-3 py-1.5 {{ ! $showDismissed ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
            Active
        </a>
        <a href="{{ route('stock-notifications.index', ['dismissed' => 1]) }}"
           class="rounded-md px-3 py-1.5 {{ $showDismissed ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
            Dismissed
        </a>
        @if($unreadCount > 0)
        <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-700">
            {{ $unreadCount }} unread
        </span>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        @if($notifications->isEmpty())
        <div class="p-8 text-center text-sm text-gray-500">
            No {{ $showDismissed ? 'dismissed' : 'active' }} stock alerts.
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">Sold out at</th>
                        <th class="px-4 py-3">Stock at</th>
                        <th class="px-4 py-3">Qty</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($notifications as $notification)
                    <tr class="{{ $notification->isUnread() ? 'bg-blue-50/40' : '' }}">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $notification->item?->code }}</div>
                            <div class="text-xs text-gray-500">{{ $notification->item?->name }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $notification->soldOutWarehouse?->name }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $notification->sourceWarehouse?->name }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $fmtNum($notification->source_stock) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $notification->source_status->colorClass() }}">
                                {{ $notification->source_status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ $notification->created_at?->format('d M Y H:i') }}
                            @if($notification->read_at)
                            <div class="text-xs">Read {{ $notification->read_at->format('d M H:i') }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('reports.warehouse-arrangement', ['warehouse_id' => $notification->sold_out_warehouse_id]) }}"
                                   class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                    Arrange
                                </a>
                                @if(! $showDismissed)
                                @if($notification->isUnread())
                                <form method="POST" action="{{ route('stock-notifications.read', $notification) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                        Mark read
                                    </button>
                                </form>
                                @endif
                                <form method="POST" action="{{ route('stock-notifications.dismiss', $notification) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-rose-200 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">
                                        Dismiss
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($notifications->hasPages())
        <div class="border-t border-gray-100 px-4 py-3">
            {{ $notifications->links() }}
        </div>
        @endif
        @endif
    </div>
</div>
@endsection
