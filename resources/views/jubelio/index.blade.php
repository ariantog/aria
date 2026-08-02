@extends('layouts.app')

@section('title', 'Jubelio Orders')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio Orders', 'href' => route('jubelio.index')],
];
@endphp

<div class="flex flex-col gap-6 p-4">
    {{-- Header --}}
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <h1 class="flex items-center gap-2 text-2xl font-bold">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 7l-1 11a1 1 0 01-1 1H8a1 1 0 01-1-1L6 7m3-3h6m-9 3h12"/></svg>
            Jubelio Orders
        </h1>

        <form method="GET" action="{{ route('jubelio.index') }}" class="flex items-center gap-2">
            <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
            <div class="relative">
                <svg class="absolute top-2.5 left-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                <input type="text" name="invoice" value="{{ $filters['invoice'] ?? '' }}" placeholder="Search Invoice..."
                       class="h-9 w-64 rounded-md border border-gray-300 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <button type="submit" class="rounded-lg bg-blue-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Search</button>
            @if(!empty($filters['status']) || !empty($filters['invoice']))
            <a href="{{ route('jubelio.index') }}" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Clear
            </a>
            @endif
        </form>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
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
        <a href="{{ route('jubelio.index', ['status' => $c['status'], 'invoice' => $filters['invoice'] ?? null]) }}"
           class="flex items-center justify-between rounded-lg border-l-4 {{ $c['border'] }} {{ $c['bg'] }} p-4 shadow-sm transition-all hover:opacity-90 {{ $isActive ? $c['ring'] : '' }}">
            <div>
                <p class="text-[10px] font-semibold tracking-wider uppercase opacity-70 md:text-xs">{{ $c['title'] }}</p>
                <p class="text-xl font-bold md:text-2xl">{{ number_format($c['value'], 0, ',', '.') }}</p>
            </div>
        </a>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 font-semibold text-gray-600 uppercase">
                    <tr>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Source</th>
                        <th class="px-6 py-4">Invoice</th>
                        <th class="px-6 py-4">Type</th>
                        <th class="px-6 py-4 text-center">Sync Status</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($orders as $order)
                    @php
                        $payloadData = json_decode($order->payload, true) ?: [];
                        $payloadDate = $payloadData['transaction_date'] ?? ($payloadData['created_date'] ?? null);
                    @endphp
                    <tr x-data="{ open: false }" class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-8 text-[9px] font-bold text-gray-400 uppercase">Sync:</span>
                                    <span class="text-[10px] font-bold text-gray-800">{{ \Carbon\Carbon::parse($order->updated_at)->translatedFormat('d M Y H:i') }}</span>
                                </div>
                                @if($payloadDate)
                                <div class="flex items-center gap-1.5">
                                    <span class="w-8 text-[9px] font-bold text-gray-400 uppercase">Trans:</span>
                                    <span class="text-[10px] font-medium text-gray-500">{{ \Carbon\Carbon::parse($payloadDate)->translatedFormat('d M Y H:i') }}</span>
                                </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if($order->source == 1)
                            <span class="inline-flex rounded-full border border-blue-500/20 bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-600">Jubelio</span>
                            @else
                            <span class="inline-flex rounded-full border border-yellow-500/20 bg-yellow-50 px-2 py-0.5 text-[10px] font-medium text-yellow-600">Aria</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-medium">
                            <a href="{{ route('jubelio.show', $order->id) }}" class="text-blue-600 hover:underline">{{ $order->invoice }}</a>
                            <div class="mt-0.5 text-[10px] text-gray-400">{{ $order->order_status }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold uppercase">{{ $order->type }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @include('jubelio.partials.sync-status-badge', ['status' => $order->status, 'errorType' => $order->error_type, 'executeBy' => $order->user->name ?? null])
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button @click="open = !open"
                                    class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-3 py-1.5 text-[10px] font-bold uppercase transition-colors hover:bg-gray-200">
                                Payload
                                <svg x-show="!open" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                <svg x-show="open" x-cloak class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                            </button>
                        </td>
                    </tr>
                    <tr x-show="open" x-cloak class="bg-gray-50/50">
                        <td colspan="6" class="px-6 py-4">
                            <div class="space-y-4">
                                <div class="max-w-full overflow-x-auto rounded-lg border border-gray-800 bg-gray-900 p-4 font-mono text-[11px] text-green-300">
                                    <pre>{{ json_encode(json_decode($order->payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $order->payload }}</pre>
                                </div>
                                @if(((($order->status == 1 && $order->error_type == 1)) || ($order->status == 2 && $order->error_type == 2)) && $order->error)
                                <div class="rounded-lg border p-4 text-xs {{ $order->error_type == 2 ? 'border-yellow-500/30 bg-yellow-50 text-yellow-700' : 'border-red-500/30 bg-red-50 text-red-700' }}">
                                    <p class="mb-1 flex items-center gap-1 font-bold">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Info:
                                    </p>
                                    <pre class="whitespace-pre-wrap">{{ $order->error }}</pre>
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-400 italic">No Jubelio orders found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $orders, 'label' => 'orders'])
    </div>
</div>
@endsection
