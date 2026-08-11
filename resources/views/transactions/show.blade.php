@extends('layouts.app')

@section('title', 'Transaction #' . $transaction->invoice_number)

@section('content')
@php
    $breadcrumbs = [
        ['title' => 'Transactions', 'href' => route('transactions.index')],
        ['title' => 'Invoice #' . $transaction->invoice_number, 'href' => route('transactions.show', $transaction->id)],
    ];

    $statuses = [
        \App\Models\Transaction::STATUS_PENDING => ['label' => 'Pending', 'color' => 'bg-yellow-100 text-yellow-800'],
        \App\Models\Transaction::STATUS_COMPLETED => ['label' => 'Completed', 'color' => 'bg-green-100 text-green-800'],
        \App\Models\Transaction::STATUS_CANCELLED => ['label' => 'Cancelled', 'color' => 'bg-red-100 text-red-800'],
    ];
    $statusKey = $transaction->status instanceof \BackedEnum ? $transaction->status->value : $transaction->status;
    $status = $statuses[$statusKey] ?? ['label' => 'Unknown', 'color' => 'bg-gray-100 text-gray-800'];

    $fmt = fn ($n) => number_format((float) $n, 0, ',', '.');
    $fmtDate = function ($d) {
        if (! $d) return '-';
        return \Illuminate\Support\Carbon::parse($d)->format('d/m/Y');
    };
@endphp

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
     x-data="{ showImage: true, showBarcode: true, showSku: false, waOpen: false, deleteConfirmOpen: false,
        get nameColSpan() {
            const c = (this.showImage?1:0) + (this.showBarcode?1:0) + (this.showSku?1:0);
            return c === 3 ? 'sm:col-span-3' : c === 2 ? 'sm:col-span-4' : c === 1 ? 'sm:col-span-5' : 'sm:col-span-6';
        } }">

    {{-- Top Action Bar --}}
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center print:hidden">
        <div class="flex items-center gap-3">
            <a href="{{ route('transactions.index') }}"
               class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Detail Transaction</h1>
                <p class="flex items-center gap-2 text-sm text-gray-500">
                    <svg class="h-3.5 w-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Invoice #{{ $transaction->invoice_number }}
                </p>
            </div>
        </div>

        @include('transactions.partials.show-actions', [
            'transaction' => $transaction,
            'hasInvoicePdf' => $hasInvoicePdf,
            'invoicePdfUrl' => $invoicePdfUrl,
            'can' => $can,
        ])
    </div>

    {{-- Delete confirm dialog --}}
    @if($can['delete_transaction'])
    <div x-show="deleteConfirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 print:hidden"
         @keydown.window.escape="deleteConfirmOpen = false">
        <div @click.away="deleteConfirmOpen = false" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900">Hapus Transaksi</h3>
            <p class="mt-2 text-sm text-gray-600">Apakah Anda yakin ingin menghapus transaksi ini? Transaksi akan dipindahkan ke daftar hapus dan dampak stok/saldo akan dibatalkan.</p>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="deleteConfirmOpen = false"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                <form method="POST" action="{{ route('transactions.destroy', $transaction->id) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Hapus</button>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- WhatsApp dialog --}}
    <div x-show="waOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 print:hidden"
         @keydown.window.escape="waOpen = false">
        <div @click.away="waOpen = false" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900">Kirim Invoice via WhatsApp</h3>
            <p class="mt-2 text-sm text-gray-600">Masukkan nomor WhatsApp. Jika PDF belum ada, invoice akan dibuat otomatis lalu link dikirim (format angka saja, contoh: 62812244226656).</p>
            <form method="POST" action="{{ route('transactions.whatsapp', $transaction->id) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="wa-phone" class="mb-1 block text-sm font-medium text-gray-700">Nomor WhatsApp</label>
                    <input type="text" id="wa-phone" name="phone" required inputmode="numeric" pattern="[0-9]{8,15}"
                           placeholder="62812244226656"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="waOpen = false"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Kirim</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Primary Info Cards --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Summary Card --}}
        <div class="overflow-hidden rounded-xl border border-blue-100 bg-white shadow-sm">
            <div class="h-2 w-full bg-blue-600"></div>
            <div class="p-6 pb-2">
                <div class="text-sm font-medium tracking-wider text-gray-500 uppercase">Grand Total</div>
                <div class="mt-1 flex items-baseline gap-1">
                    <span class="text-3xl font-black text-blue-600">IDR</span>
                    <span class="text-4xl font-black tracking-tighter tabular-nums">{{ $fmt(abs($transaction->grand_total)) }}</span>
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
                    <div class="flex justify-between text-sm">
                        <span class="flex items-center gap-1.5 text-gray-500">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 8V3a2 2 0 012-2z" transform="translate(0)"/></svg> Submit Source
                        </span>
                        @if((int) $transaction->submit_type === 2)
                            <span class="inline-flex items-center gap-1 rounded-md border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-xs font-bold text-amber-500">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> cron jubelio
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-md border border-blue-500/20 bg-blue-500/10 px-2 py-0.5 text-xs font-bold text-blue-500">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg> aria submit
                            </span>
                        @endif
                    </div>
                    @if($transaction->user)
                    <div class="flex justify-between text-sm">
                        <span class="flex items-center gap-1.5 text-gray-500">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> Created By
                        </span>
                        <span class="font-medium underline decoration-blue-500/30">{{ $transaction->user->name }}</span>
                    </div>
                    @endif
                    @if($transaction->sync_cek && ($can['jubelio_transaction_sync'] ?? false))
                    <div class="flex justify-between pt-2 text-sm">
                        <span class="flex items-center gap-1.5 font-bold text-blue-600">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Sinkron Jubelio
                        </span>
                        <a href="/jubelio-transaction/{{ $transaction->id }}/detail-sync"
                           class="inline-flex items-center rounded-md border border-gray-300 px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-blue-50">
                            Kelola Sinkron
                            <svg class="ml-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sender Info --}}
        @include('transactions.partials.show-party', [
            'party' => $transaction->sender,
            'label' => $config['sender_label'],
            'sub' => 'Origin of these items',
            'direction' => 'From',
            'accent' => 'blue',
            'iconArrow' => false,
            'emptyText' => 'No sender info',
            'sideStatus' => [
                'submitted' => $transaction->a_synced,
                'needsSync' => in_array($transaction->sync_cek, ['S', 'B'], true),
                'jubelioLocation' => $transaction->jubelio_a,
                'isFromJubelio' => $transaction->is_from_jubelio,
                'role' => 'sender',
            ],
        ])

        {{-- Receiver Info --}}
        @include('transactions.partials.show-party', [
            'party' => $transaction->receiver,
            'label' => $config['receiver_label'],
            'sub' => 'Destination of these items',
            'direction' => 'To',
            'accent' => 'green',
            'iconArrow' => true,
            'emptyText' => 'No receiver info',
            'sideStatus' => [
                'submitted' => $transaction->b_synced,
                'needsSync' => in_array($transaction->sync_cek, ['R', 'B'], true),
                'jubelioLocation' => $transaction->jubelio_b,
                'isFromJubelio' => $transaction->is_from_jubelio,
                'role' => 'receiver',
            ],
        ])
    </div>

    @include('transactions.partials.jubelio-sync', ['transaction' => $transaction, 'jubelioSync' => $jubelioSync ?? []])

    {{-- Items Section --}}
    <div class="rounded-xl bg-white shadow-md print:shadow-none">
        <div class="flex flex-col justify-between gap-4 p-6 pb-4 md:flex-row md:items-center">
            <div>
                <div class="flex items-center gap-2 text-lg font-semibold">
                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Items List
                </div>
                <p class="text-sm text-gray-500">Requested items in this transaction ({{ $transaction->total_items }})</p>
            </div>

            {{-- Column Controls --}}
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
                <div class="col-span-1 text-right font-black">Disc(%)</div>
                <div class="col-span-2 text-right font-black">Subtotal</div>
            </div>

            {{-- Rows --}}
            <div class="divide-y print:block print:divide-y">
                @foreach($transaction->details as $detail)
                @php $item = $detail->item; @endphp
                <div class="group flex flex-col gap-4 p-4 transition-colors hover:bg-gray-50 sm:grid sm:grid-cols-12 sm:items-center sm:gap-4 sm:p-3 sm:text-sm print:grid print:grid-cols-12 print:items-center print:gap-4 print:p-3">
                    {{-- Mobile: image & name header --}}
                    <div class="flex items-start gap-3 sm:hidden">
                        <div class="relative flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded border bg-white shadow-sm" x-show="showImage">
                            @if($item?->image_url)
                                <img src="{{ $item->image_url }}" alt="{{ $item?->name }}" class="h-full w-full object-cover">
                            @else
                                <svg class="h-7 w-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            @endif
                        </div>
                        <div class="flex flex-col">
                            <div class="font-bold text-gray-900">{{ $item?->name }}</div>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <span class="font-mono text-[10px] font-medium text-blue-600" x-show="showBarcode">#{{ $item?->id }}</span>
                                @if($item?->code)
                                <span class="font-mono text-[10px] italic text-gray-500" x-show="showSku">SKU: {{ $item?->code }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Desktop: Image --}}
                    <div class="hidden col-span-1 text-center sm:block print:block" x-show="showImage">
                        <div class="relative mx-auto flex h-12 w-12 items-center justify-center overflow-hidden rounded border bg-white shadow-sm transition-transform duration-200 group-hover:scale-105">
                            @if($item?->image_url)
                                <img src="{{ $item->image_url }}" alt="{{ $item?->name }}" class="h-full w-full object-cover">
                            @else
                                <svg class="h-6 w-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            @endif
                        </div>
                    </div>

                    {{-- Desktop: Barcode --}}
                    <div class="hidden col-span-1 font-mono text-xs sm:block print:block" x-show="showBarcode">
                        <a href="{{ $item ? route('items.show', $item->id) : '#' }}" class="text-blue-600 hover:underline">{{ $item?->id }}</a>
                    </div>

                    {{-- Desktop: SKU --}}
                    <div class="hidden col-span-1 font-mono text-xs italic text-gray-500 sm:block print:block" x-show="showSku">{{ $item?->code ?: '-' }}</div>

                    {{-- Desktop: Name --}}
                    <div class="hidden sm:flex flex-col print:flex" :class="nameColSpan">
                        <span class="font-bold text-gray-900">{{ $item?->name }}</span>
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

                    <div class="flex items-center justify-between sm:col-span-1 sm:block sm:text-right print:block print:text-right">
                        <span class="text-[10px] font-bold text-gray-500 uppercase sm:hidden print:hidden">Disc</span>
                        @if($detail->discount > 0)
                            <span class="inline-flex h-5 items-center rounded-md border border-dashed border-red-300 bg-red-50 px-1.5 text-[10px] font-bold text-red-600">-{{ number_format((float) $detail->discount, 2, ',', '.') }}%</span>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between border-t pt-3 sm:col-span-2 sm:block sm:border-0 sm:pt-0 sm:text-right print:block print:text-right">
                        <span class="text-[10px] font-bold text-gray-900 uppercase sm:hidden print:hidden">Subtotal</span>
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
        {{-- Notes Card --}}
        <div class="flex h-full flex-col rounded-xl bg-white shadow-sm">
            <div class="border-b bg-gray-50/50 p-6 py-4">
                <div class="flex items-center gap-2 text-sm font-bold">
                    <svg class="h-4 w-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Internal Notes
                </div>
            </div>
            <div class="flex-1 p-6 pt-4">
                @if($transaction->notes)
                    <p class="border-l-2 border-blue-200 py-1 pl-4 text-sm leading-relaxed whitespace-pre-line italic text-gray-500">"{{ $transaction->notes }}"</p>
                @else
                    <div class="flex h-full flex-col items-center justify-center py-4 text-gray-400 opacity-30">
                        <svg class="mb-1 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs italic">No notes added</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Financial Summary Card --}}
        <div class="rounded-xl border border-blue-200 bg-blue-50/20 shadow-sm">
            <div class="space-y-3 p-6 tabular-nums">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Subtotal</span>
                    <span class="font-bold">{{ $fmt($transaction->total) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Invoice Discount ({{ $transaction->discount_percent ?? 0 }}%)</span>
                    <span class="font-bold text-red-600">-{{ $fmt($transaction->discount) }}</span>
                </div>
                <hr class="border-dashed">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500 italic underline decoration-dotted">Adjustment</span>
                    <span class="font-bold {{ $transaction->adjustment < 0 ? 'text-red-500' : 'text-green-500' }}">{{ $transaction->adjustment > 0 ? '+' : '' }}{{ $fmt($transaction->adjustment) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">PPN / Tax</span>
                    <span class="font-bold">{{ $fmt($transaction->tax_amount) }}</span>
                </div>
                <div class="pt-2">
                    <div class="flex items-center justify-between rounded-lg bg-blue-600 p-4 text-white shadow-lg shadow-blue-500/20">
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black tracking-widest text-blue-100/70 uppercase">Grand Total</span>
                            <span class="text-xs font-medium italic text-blue-100">Net Amount Payable</span>
                        </div>
                        <span class="text-2xl font-black">IDR {{ $fmt(abs($transaction->grand_total)) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Print Footer (Signature) --}}
    <div class="mt-12 hidden grid-cols-3 gap-8 text-center text-xs print:grid">
        <div class="space-y-16">
            <p class="font-bold tracking-wider uppercase">Authorized By</p>
            <div class="mx-auto w-2/3 border-b border-black"></div>
            <p>( {{ $transaction->user?->name ?: '________________' }} )</p>
        </div>
        <div class="space-y-16">
            <p class="font-bold tracking-wider uppercase">Origin Sign</p>
            <div class="mx-auto w-2/3 border-b border-black"></div>
            <p>( {{ $transaction->sender?->name ?: '________________' }} )</p>
        </div>
        <div class="space-y-16">
            <p class="font-bold tracking-wider uppercase">Received By</p>
            <div class="mx-auto w-2/3 border-b border-black"></div>
            <p>( {{ $transaction->receiver?->name ?: '________________' }} )</p>
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
        .bg-blue-600 { background-color: #000 !important; color: white !important; -webkit-print-color-adjust: exact; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>
@endpush
