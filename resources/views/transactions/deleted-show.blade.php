@extends('layouts.app')

@section('title', 'Deleted Transaction #' . $transaction->invoice)

@section('content')
@php
    $breadcrumbs = [
        ['title' => 'Transactions', 'href' => route('transactions.index')],
        ['title' => 'Deleted', 'href' => route('transactions.deleted.index')],
        ['title' => 'Invoice #' . $transaction->invoice, 'href' => route('transactions.deleted.show', $transaction->id)],
    ];

    $statuses = [
        1 => ['label' => 'Pending', 'color' => 'bg-yellow-100 text-yellow-800'],
        2 => ['label' => 'Completed', 'color' => 'bg-green-100 text-green-800'],
        3 => ['label' => 'Cancelled', 'color' => 'bg-red-100 text-red-800'],
    ];
    $status = $statuses[$transaction->status] ?? ['label' => 'Unknown', 'color' => 'bg-gray-100 text-gray-800'];

    $fmt = fn ($n) => format_amount($n);
    $grandTotalFormatted = $fmt(abs($transaction->real_total));
    $grandTotalHeroClass = \App\Support\AmountFormatter::displayTextClass($grandTotalFormatted, 'hero');
    $grandTotalCompactClass = \App\Support\AmountFormatter::displayTextClass($grandTotalFormatted, 'compact');
    $fmtDate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '-';
    $fmtDateTime = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y H:i') : '-';
@endphp

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
     x-data="{ showImage: true, showBarcode: true, showSku: false,
        get nameColSpan() { return (this.showImage && this.showBarcode && this.showSku) ? 'sm:col-span-3' : 'sm:col-span-6'; } }">

    {{-- Top Action Bar --}}
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center print:hidden">
        <div class="flex items-center gap-3">
            <a href="{{ route('transactions.deleted.index') }}"
               class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight">Deleted Transaction</h1>
                    <span class="inline-flex items-center rounded-md bg-red-600 px-2 py-0.5 text-xs font-semibold text-white uppercase">Deleted</span>
                </div>
                <p class="flex items-center gap-2 text-sm text-gray-500">
                    <svg class="h-3.5 w-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Invoice #{{ $transaction->invoice }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print
            </button>
        </div>
    </div>

    {{-- Deleted Info Banner --}}
    <div class="flex items-center gap-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        <div>
            <p class="text-sm font-bold">This transaction was deleted on {{ $fmtDateTime($transaction->deleted_at) }}</p>
            <p class="text-xs opacity-80">Stock and balance impacts were reversed when it was removed from the active list.</p>
        </div>
    </div>

    {{-- Primary Info Cards --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Summary Card --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="h-2 w-full bg-zinc-500"></div>
            <div class="p-6 pb-2">
                <div class="text-sm font-medium tracking-wider text-gray-500 uppercase">Grand Total</div>
                <div class="mt-1 min-w-0">
                    <div class="text-xs font-semibold text-zinc-500 sm:text-sm">IDR</div>
                    <div class="{{ $grandTotalHeroClass }} tabular-nums break-all text-zinc-900">{{ $grandTotalFormatted }}</div>
                </div>
            </div>
            <div class="space-y-4 p-6 pt-4">
                <div class="flex items-center justify-between rounded-lg border border-dashed bg-gray-50 p-3">
                    <div class="text-sm font-medium">Status</div>
                    <span class="inline-flex items-center rounded-full px-3 py-0.5 text-xs font-semibold {{ $status['color'] }}">{{ $status['label'] }}</span>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="flex items-center gap-1.5 text-gray-500">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> Date
                        </span>
                        <span class="font-semibold">{{ $fmtDate($transaction->date) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="flex items-center gap-1.5 text-gray-500">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Type
                        </span>
                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 capitalize">{{ $config['type_slug'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sender Info --}}
        @include('transactions.partials.deleted-party', [
            'party' => $transaction->sender,
            'label' => $config['sender_label'],
            'sub' => 'Origin of these items',
            'direction' => 'From',
            'iconArrow' => false,
            'emptyText' => 'No sender info',
        ])

        {{-- Receiver Info --}}
        @include('transactions.partials.deleted-party', [
            'party' => $transaction->receiver,
            'label' => $config['receiver_label'],
            'sub' => 'Destination of these items',
            'direction' => 'To',
            'iconArrow' => true,
            'emptyText' => 'No receiver info',
        ])
    </div>

    {{-- Items Section --}}
    <div class="rounded-xl bg-white shadow-md print:shadow-none">
        <div class="flex flex-col justify-between gap-4 p-6 pb-4 md:flex-row md:items-center">
            <div>
                <div class="flex items-center gap-2 text-lg font-semibold">
                    <svg class="h-5 w-5 text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Items List
                </div>
                <p class="text-sm text-gray-500">Requested items in this transaction ({{ $transaction->total_items }})</p>
            </div>

            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-full border border-dashed bg-gray-50 px-4 py-2 print:hidden">
                <span class="text-[10px] font-black tracking-widest text-gray-500 uppercase">View:</span>
                <label class="flex cursor-pointer items-center gap-2 text-xs font-bold">
                    <input type="checkbox" x-model="showImage" class="h-4 w-4 rounded border-gray-300"> Image
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-xs font-bold">
                    <input type="checkbox" x-model="showBarcode" class="h-4 w-4 rounded border-gray-300"> Barcode
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-xs font-bold">
                    <input type="checkbox" x-model="showSku" class="h-4 w-4 rounded border-gray-300"> SKU
                </label>
            </div>
        </div>

        <div class="flex flex-col print:block">
            {{-- Header --}}
            <div class="hidden grid-cols-12 gap-4 border-y bg-gray-50 p-3 text-[10px] font-bold tracking-wider text-gray-500 uppercase sm:grid print:grid">
                <div class="col-span-1 text-center font-black" x-show="showImage">Img</div>
                <div class="col-span-1 font-black" x-show="showBarcode">Barcode</div>
                <div class="col-span-1 font-black" x-show="showSku">SKU</div>
                <div class="font-black" :class="nameColSpan">Item Name</div>
                <div class="col-span-1 text-center font-black">Qty</div>
                <div class="col-span-2 text-right font-black">Price</div>
                <div class="col-span-1 text-center font-black">Disc(%)</div>
                <div class="col-span-2 text-right font-black">Subtotal</div>
            </div>

            {{-- Rows --}}
            <div class="divide-y print:block print:divide-y">
                @foreach($transaction->details as $detail)
                @php
                    $item = $detail->item;
                    $itemUrl = $item ? route('items.show', $item->id) : null;
                @endphp
                <div class="group flex flex-col gap-4 p-4 transition-colors hover:bg-gray-50 sm:grid sm:grid-cols-12 sm:items-center sm:gap-4 sm:p-3 sm:text-sm print:grid print:grid-cols-12 print:items-center print:gap-4 print:p-3">
                    {{-- Mobile --}}
                    <div class="flex items-start gap-3 sm:hidden">
                        @if($itemUrl)
                            <a href="{{ $itemUrl }}" class="relative flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded border bg-white shadow-sm transition-transform hover:scale-105" x-show="showImage">
                                @if($item->image_url)
                                    <img src="{{ $item->image_url }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                                @else
                                    <svg class="h-7 w-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                @endif
                            </a>
                        @else
                            <div class="relative flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded border bg-white shadow-sm" x-show="showImage">
                                @if($item?->image_url)
                                    <img src="{{ $item->image_url }}" alt="{{ $item?->name }}" class="h-full w-full object-cover">
                                @else
                                    <svg class="h-7 w-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                @endif
                            </div>
                        @endif
                        <div class="flex flex-col">
                            @if($itemUrl)
                                <a href="{{ $itemUrl }}" class="font-bold text-blue-600 hover:underline">{{ $item->name }}</a>
                            @else
                                <div class="font-bold text-zinc-900">{{ $item?->name }}</div>
                            @endif
                            <div class="flex flex-wrap gap-2 pt-1">
                                @if($itemUrl)
                                    <a href="{{ $itemUrl }}" class="font-mono text-[10px] font-medium text-blue-600 hover:underline" x-show="showBarcode">#{{ $item->id }}</a>
                                @else
                                    <span class="font-mono text-[10px] font-medium text-blue-600" x-show="showBarcode">#{{ $item?->id }}</span>
                                @endif
                                @if($item?->code)
                                    @if($itemUrl)
                                        <a href="{{ $itemUrl }}" class="font-mono text-[10px] italic text-gray-500 hover:text-blue-600 hover:underline" x-show="showSku">SKU: {{ $item->code }}</a>
                                    @else
                                        <span class="font-mono text-[10px] italic text-gray-500" x-show="showSku">SKU: {{ $item?->code }}</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="hidden col-span-1 text-center sm:block print:block" x-show="showImage">
                        @if($itemUrl)
                            <a href="{{ $itemUrl }}" class="relative mx-auto flex h-12 w-12 items-center justify-center overflow-hidden rounded border bg-white shadow-sm transition-transform duration-200 group-hover:scale-105">
                                @if($item->image_url)
                                    <img src="{{ $item->image_url }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                                @else
                                    <svg class="h-6 w-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                @endif
                            </a>
                        @else
                            <div class="relative mx-auto flex h-12 w-12 items-center justify-center overflow-hidden rounded border bg-white shadow-sm transition-transform duration-200 group-hover:scale-105">
                                @if($item?->image_url)
                                    <img src="{{ $item->image_url }}" alt="{{ $item?->name }}" class="h-full w-full object-cover">
                                @else
                                    <svg class="h-6 w-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="hidden col-span-1 font-mono text-xs sm:block print:block" x-show="showBarcode">
                        @if($itemUrl)
                            <a href="{{ $itemUrl }}" class="text-blue-600 hover:underline">{{ $item->id }}</a>
                        @else
                            <span>{{ $item?->id }}</span>
                        @endif
                    </div>

                    <div class="hidden col-span-1 font-mono text-xs italic text-gray-500 sm:block print:block" x-show="showSku">
                        @if($itemUrl && $item->code)
                            <a href="{{ $itemUrl }}" class="hover:text-blue-600 hover:underline">{{ $item->code }}</a>
                        @else
                            {{ $item?->code ?: '-' }}
                        @endif
                    </div>

                    <div class="hidden sm:flex flex-col print:flex" :class="nameColSpan">
                        @if($itemUrl)
                            <a href="{{ $itemUrl }}" class="font-bold text-blue-600 hover:underline">{{ $item->name }}</a>
                        @else
                            <span class="font-bold text-zinc-900">{{ $item?->name }}</span>
                        @endif
                        <span class="mt-0.5 line-clamp-1 text-[10px] leading-tight italic text-gray-500">{{ $detail->notes ?: $item?->description }}</span>
                    </div>

                    <div class="flex items-center justify-between sm:col-span-1 sm:block sm:text-center print:block print:text-center">
                        <span class="text-[10px] font-bold text-gray-500 uppercase sm:hidden print:hidden">Qty</span>
                        <span class="inline-flex items-center justify-center rounded bg-gray-100 px-2 py-1 text-xs font-black sm:bg-transparent sm:p-0">{{ $detail->quantity }}</span>
                    </div>

                    <div class="flex items-center justify-between sm:col-span-2 sm:block sm:text-right print:block print:text-right">
                        <span class="text-[10px] font-bold text-gray-500 uppercase sm:hidden print:hidden">Price</span>
                        <span class="font-medium">{{ $fmt($detail->price) }}</span>
                    </div>

                    <div class="flex items-center justify-between sm:col-span-1 sm:block sm:text-center print:block print:text-center">
                        <span class="text-[10px] font-bold text-gray-500 uppercase sm:hidden print:hidden">Disc</span>
                        @if($detail->discount > 0)
                            <span class="inline-flex h-5 items-center rounded-md border border-dashed border-red-300 bg-red-50 px-1.5 text-[10px] font-bold text-red-600">-{{ number_format((float) $detail->discount, 2, ',', '.') }}%</span>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between border-t pt-3 sm:col-span-2 sm:block sm:border-0 sm:pt-0 sm:text-right print:block print:text-right">
                        <span class="text-[10px] font-bold text-zinc-900 uppercase sm:hidden print:hidden">Subtotal</span>
                        <span class="text-lg font-black text-blue-700 sm:text-sm">{{ $fmt($detail->total) }}</span>
                    </div>

                    @if($detail->notes)
                    <div class="mt-1 rounded bg-gray-50 p-2 text-xs italic text-gray-500 sm:hidden">📝 {{ $detail->notes }}</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Footer Totals & Summary --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="flex h-full flex-col rounded-xl bg-white shadow-sm">
            <div class="border-b bg-gray-50/50 p-6 py-4">
                <div class="flex items-center gap-2 text-sm font-bold">
                    <svg class="h-4 w-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Internal Notes
                </div>
            </div>
            <div class="flex-1 p-6 pt-4">
                @if($transaction->notes)
                    <p class="border-l-2 border-zinc-200 py-1 pl-4 text-sm leading-relaxed whitespace-pre-line italic text-gray-500">"{{ $transaction->notes }}"</p>
                @else
                    <div class="flex h-full flex-col items-center justify-center py-4 text-gray-400 opacity-30">
                        <svg class="mb-1 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs italic">No notes</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50/20 shadow-sm">
            <div class="space-y-3 p-6 tabular-nums">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Subtotal</span>
                    <span class="font-bold">{{ $fmt($transaction->total) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Invoice Discount ({{ $transaction->discount ?? 0 }}%)</span>
                    <span class="font-bold text-red-600">-{{ $fmt($transaction->discount) }}</span>
                </div>
                <hr class="border-dashed">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500 italic underline decoration-dotted">Adjustment</span>
                    <span class="font-bold {{ $transaction->adjustment < 0 ? 'text-red-500' : 'text-green-500' }}">{{ $transaction->adjustment > 0 ? '+' : '' }}{{ $fmt($transaction->adjustment) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Tax / VAT</span>
                    <span class="font-bold">{{ $fmt($transaction->ppn) }}</span>
                </div>
                <div class="pt-2">
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-800 p-4 text-white shadow-lg">
                        <div class="flex min-w-0 flex-shrink-0 flex-col">
                            <span class="text-[10px] font-black tracking-widest text-zinc-300 uppercase">Grand Total</span>
                            <span class="text-xs font-medium italic text-zinc-400">Net Amount</span>
                        </div>
                        <span class="min-w-0 break-all text-right tabular-nums {{ $grandTotalCompactClass }}">IDR {{ $grandTotalFormatted }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('head-css')
<style>
    @media print {
        body { background: white !important; }
        .print\:hidden { display: none !important; }
        .shadow-sm, .shadow-md, .shadow-lg { box-shadow: none !important; }
        .bg-zinc-800 { background-color: #000 !important; color: white !important; -webkit-print-color-adjust: exact; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>
@endpush
