@extends('layouts.app')

@section('title', 'Transaction #' . $transaction->invoice)

@section('content')
@php
    $breadcrumbs = [
        ['title' => 'Transactions', 'href' => route('transactions.index')],
        ['title' => 'Invoice #' . $transaction->invoice, 'href' => route('transactions.show', $transaction->id)],
    ];

    $statuses = [
        \App\Models\Transaction::STATUS_PENDING => ['label' => 'Pending', 'color' => 'bg-yellow-100 text-yellow-800'],
        \App\Models\Transaction::STATUS_COMPLETED => ['label' => 'Completed', 'color' => 'bg-green-100 text-green-800'],
        \App\Models\Transaction::STATUS_CANCELLED => ['label' => 'Cancelled', 'color' => 'bg-red-100 text-red-800'],
    ];
    $statusKey = $transaction->status instanceof \BackedEnum ? $transaction->status->value : $transaction->status;
    $status = $statuses[$statusKey] ?? ['label' => 'Unknown', 'color' => 'bg-gray-100 text-gray-800'];

    $fmt = fn ($n) => format_amount($n);
    $grandTotalFormatted = $fmt(abs($transaction->real_total));
    $fmtDate = function ($d) {
        if (! $d) return '-';
        return \Illuminate\Support\Carbon::parse($d)->format('d M Y');
    };
@endphp

<div class="flex flex-col gap-6 p-4 md:p-6"
     x-data="{ waOpen: false, deleteConfirmOpen: false }">

    {{-- Page header --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm print:hidden">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('transactions.index') }}"
                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </a>
                    <h1 class="text-xl font-semibold text-gray-900">Invoice #{{ $transaction->invoice }}</h1>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $status['color'] }}">{{ $status['label'] }}</span>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    Date: {{ $fmtDate($transaction->date) }}
                    · <span class="capitalize">{{ $config['type_slug'] }}</span>
                    @if($transaction->user)
                        · Created by {{ $transaction->user->name }}
                    @endif
                </p>
            </div>
            @include('transactions.partials.show-actions', [
                'transaction' => $transaction,
                'hasInvoicePdf' => $hasInvoicePdf,
                'invoicePdfUrl' => $invoicePdfUrl,
                'can' => $can,
            ])
        </div>
    </div>

    {{-- Modals --}}
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

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        {{-- Main: line items + summary --}}
        <div class="xl:col-span-8">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 class="text-base font-semibold text-gray-900">Transaction Details</h2>
                    <p class="mt-0.5 text-sm text-gray-500">{{ $transaction->total_items }} item(s)</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-5 py-3">#</th>
                                <th class="px-5 py-3">Product</th>
                                <th class="px-5 py-3 text-right">Qty</th>
                                <th class="px-5 py-3 text-right">Unit Price</th>
                                <th class="px-5 py-3 text-right">Discount</th>
                                <th class="px-5 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($transaction->details as $index => $detail)
                                @php $item = $detail->item; @endphp
                                <tr class="hover:bg-gray-50/80">
                                    <td class="whitespace-nowrap px-5 py-3 text-gray-500">{{ $index + 1 }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3 min-w-[12rem]">
                                            @if($item?->image_url)
                                                <img src="{{ $item->image_url }}" alt="" class="h-10 w-10 rounded border border-gray-200 object-cover">
                                            @endif
                                            <div class="min-w-0">
                                                @if($item)
                                                    <a href="{{ route('items.show', $item->id) }}" class="font-medium text-gray-900 hover:text-blue-600 hover:underline">{{ $item->name }}</a>
                                                @else
                                                    <span class="font-medium text-gray-900">—</span>
                                                @endif
                                                @if($item?->code)
                                                    <p class="font-mono text-xs text-gray-500">{{ $item->code }}</p>
                                                @endif
                                                @if($detail->notes)
                                                    <p class="mt-0.5 text-xs italic text-gray-400">{{ $detail->notes }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-gray-900">{{ $fmt($detail->quantity) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-gray-700">{{ $fmt($detail->price) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-gray-700">
                                        @if((float) $detail->discount > 0)
                                            {{ number_format((float) $detail->discount, 2, ',', '.') }}%
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-medium tabular-nums text-gray-900">{{ $fmt($detail->total) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-400">No line items for this transaction.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end border-t border-gray-100 bg-gray-50 px-5 py-5">
                    <div class="w-full max-w-sm space-y-2 text-sm tabular-nums">
                        <h3 class="mb-3 font-semibold text-gray-900">Order summary</h3>
                        <div class="flex items-center justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span class="font-medium text-gray-900">{{ $fmt($transaction->total) }}</span>
                        </div>
                        @if((float) $transaction->discount > 0)
                        <div class="flex items-center justify-between text-gray-600">
                            <span>Discount ({{ $transaction->discount }}%)</span>
                            <span class="font-medium text-red-600">-{{ $fmt($transaction->discount) }}</span>
                        </div>
                        @endif
                        @if((float) $transaction->adjustment != 0)
                        <div class="flex items-center justify-between text-gray-600">
                            <span>Adjustment</span>
                            <span class="font-medium {{ $transaction->adjustment < 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ $transaction->adjustment > 0 ? '+' : '' }}{{ $fmt($transaction->adjustment) }}
                            </span>
                        </div>
                        @endif
                        @if((float) $transaction->ppn > 0)
                        <div class="flex items-center justify-between text-gray-600">
                            <span>PPN / Tax</span>
                            <span class="font-medium text-gray-900">{{ $fmt($transaction->ppn) }}</span>
                        </div>
                        @endif
                        <div class="flex items-center justify-between border-t border-gray-200 pt-2 text-base font-semibold text-gray-900">
                            <span>Total</span>
                            <span class="break-all text-right">IDR {{ $grandTotalFormatted }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6 xl:col-span-4">
            @include('transactions.partials.show-party-sidebar', [
                'title' => $config['sender_label'],
                'party' => $transaction->sender,
                'emptyText' => 'No sender info',
                'sideStatus' => [
                    'submitted' => $transaction->a_synced,
                    'needsSync' => in_array($transaction->sync_cek, ['S', 'B'], true),
                    'jubelioLocation' => $transaction->jubelio_a,
                ],
            ])

            @include('transactions.partials.show-party-sidebar', [
                'title' => $config['receiver_label'],
                'party' => $transaction->receiver,
                'emptyText' => 'No receiver info',
                'sideStatus' => [
                    'submitted' => $transaction->b_synced,
                    'needsSync' => in_array($transaction->sync_cek, ['R', 'B'], true),
                    'jubelioLocation' => $transaction->jubelio_b,
                ],
            ])

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 class="text-base font-semibold text-gray-900">Notes</h2>
                </div>
                <div class="px-5 py-4">
                    @if($transaction->notes)
                        <p class="whitespace-pre-line text-sm leading-relaxed text-gray-600">{{ $transaction->notes }}</p>
                    @else
                        <p class="text-sm italic text-gray-400">No notes added.</p>
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 class="text-base font-semibold text-gray-900">Transaction Info</h2>
                </div>
                <dl class="space-y-3 px-5 py-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Submit source</dt>
                        <dd class="mt-0.5 text-gray-900">{{ (int) $transaction->submit_type === 2 ? 'Cron Jubelio' : 'Aria submit' }}</dd>
                    </div>
                    @if($transaction->sync_cek && ($can['jubelio_transaction_sync'] ?? false))
                    <div>
                        <dt class="text-gray-500">Jubelio sync</dt>
                        <dd class="mt-0.5">
                            <a href="/jubelio-transaction/{{ $transaction->id }}/detail-sync"
                               class="font-medium text-blue-600 hover:underline">Manage sync</a>
                        </dd>
                    </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    @include('transactions.partials.jubelio-sync', ['transaction' => $transaction, 'jubelioSync' => $jubelioSync ?? []])

    {{-- Print signatures --}}
    <div class="mt-4 hidden grid-cols-3 gap-8 text-center text-xs print:grid">
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
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>
@endpush
