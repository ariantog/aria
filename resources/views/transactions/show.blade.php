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
    $grandTotalFormatted = $fmt($transaction->total);
    $grandTotalHeroClass = \App\Support\AmountFormatter::displayTextClass($grandTotalFormatted, 'hero');
    $grandTotalCompactClass = \App\Support\AmountFormatter::displayTextClass($grandTotalFormatted, 'compact');
    $fmtDate = function ($d) {
        if (! $d) return '-';
        return \Illuminate\Support\Carbon::parse($d)->format('d/m/Y');
    };
    $noteText = $transaction->description ?: ($transaction->notes ?: '');
    $hasRecordedPpn = (float) $transaction->ppn > 0;
    $ppnDppDisplay = $transaction->ppn_dpp !== null ? $fmt($transaction->ppn_dpp) : '-';
    $cashTotalAbs = abs((float) $transaction->total);
@endphp

<div class="flex h-full flex-1 flex-col gap-3 overflow-x-auto rounded-xl p-3 sm:gap-4 sm:p-4"
     x-data="transactionShowPage({{ $transaction->id }}, @js($noteText), @js((bool) ($can['edit_transaction'] ?? false)), @js((bool) ($canEditPpn ?? false)), @js((float) $transaction->ppn), @js($transaction->ppn_dpp !== null ? (float) $transaction->ppn_dpp : null), @js($transaction->pph !== null ? (float) $transaction->pph : null), @js((float) ($ppn_rate ?? 11)), @js((float) ($pph_rate ?? 10)), @js($cashTotalAbs))">

    {{-- Top Action Bar --}}
    <div class="flex flex-col gap-3 print:hidden md:flex-row md:items-start md:justify-between">
        <div class="flex min-w-0 items-start gap-2.5 sm:gap-3">
            <a href="{{ route('transactions.index') }}"
               class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div class="min-w-0">
                <h1 class="text-lg font-bold tracking-tight sm:text-2xl">Detail Transaction</h1>
                <p class="flex items-center gap-1.5 text-xs text-gray-500 sm:gap-2 sm:text-sm">
                    <svg class="h-3.5 w-3.5 shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Invoice #{{ $transaction->invoice }}
                    @if($can['edit_invoice'] ?? false)
                    <button type="button" @click="invoiceModalOpen = true" data-testid="edit-tx-invoice"
                            class="inline-flex items-center rounded border border-gray-300 bg-white px-1.5 py-0.5 text-[10px] font-medium text-gray-600 hover:bg-gray-50">
                        Edit
                    </button>
                    @endif
                    @if($invoiceSettlement)
                        @include('invoice-maker.partials.status-badge', [
                            'status' => $invoiceSettlement['status'],
                            'label' => $invoiceSettlement['status_label'],
                        ])
                    @endif
                </p>
            </div>
        </div>

        <div class="w-full shrink-0 md:w-auto">
            @include('transactions.partials.show-actions', [
                'transaction' => $transaction,
                'hasInvoicePdf' => $hasInvoicePdf,
                'invoicePdfUrl' => $invoicePdfUrl,
                'can' => $can,
            ])
        </div>
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

    @if($can['edit_transaction'] ?? false)
    {{-- Edit description dialog --}}
    <div x-show="noteModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 print:hidden"
         @keydown.window.escape="noteModalOpen = false">
        <div @click.away="noteModalOpen = false" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900">Edit Description</h3>
            <p class="mt-1 text-sm text-gray-500">Update the transaction note shown on this page and in the list.</p>
            <div class="mt-4">
                <label for="tx-show-note-textarea" class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                <textarea id="tx-show-note-textarea" x-ref="noteTextarea" x-model="noteDraft" rows="4"
                          class="w-full resize-y rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                          placeholder="Add a note…"></textarea>
                <p x-show="noteError" x-text="noteError" class="mt-2 text-sm text-rose-600"></p>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="noteModalOpen = false"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" @click="saveNote()" :disabled="noteSaving"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    <span x-show="!noteSaving">Save</span>
                    <span x-show="noteSaving">Saving…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    @if($canEditPpn ?? false)
    {{-- Edit PPN dialog --}}
    <div x-show="ppnModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 print:hidden"
         @keydown.window.escape="ppnModalOpen = false">
        <div @click.away="ppnModalOpen = false" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900">Edit PPN</h3>
            <p class="mt-1 text-sm text-gray-500">DPP, PPN, and optional PPh are calculated from the transaction total. Edit any field if the invoice differs slightly.</p>
            <div class="mt-4 space-y-3">
                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                    <input type="checkbox" x-model="ppnRecord" @change="onPpnRecordToggle()" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    Record PPN for this transaction
                </label>
                <label x-show="ppnRecord" x-cloak class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                    <input type="checkbox" x-model="pphRecord" @change="onPphRecordToggle()" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    Include PPh withholding (<span x-text="pphRate"></span>% of DPP)
                </label>
                <div x-show="ppnRecord" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label for="tx-show-ppn-dpp" class="mb-1 block text-sm font-medium text-gray-700">DPP (Rp)</label>
                        <input id="tx-show-ppn-dpp" type="number" min="0" step="any" x-model.number="ppnDppDraft"
                               x-ref="ppnDppInput"
                               @input="ppnManual = true"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="tx-show-ppn-tax" class="mb-1 block text-sm font-medium text-gray-700">PPN (Rp)</label>
                        <input id="tx-show-ppn-tax" type="number" min="0" step="any" x-model.number="ppnTaxDraft"
                               @input="ppnManual = true"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div x-show="pphRecord" x-cloak>
                        <label for="tx-show-pph-tax" class="mb-1 block text-sm font-medium text-gray-700">PPh (Rp)</label>
                        <input id="tx-show-pph-tax" type="number" min="0" step="any" x-model.number="pphTaxDraft"
                               @input="ppnManual = true"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>
                <p x-show="ppnError" x-text="ppnError" class="text-sm text-rose-600"></p>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="ppnModalOpen = false"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" @click="savePpn()" :disabled="ppnSaving"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    <span x-show="!ppnSaving">Save</span>
                    <span x-show="ppnSaving">Saving…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    @if($can['edit_invoice'] ?? false)
    <div x-show="invoiceModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 print:hidden"
         @keydown.window.escape="invoiceModalOpen = false">
        <div @click.away="invoiceModalOpen = false" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900">Change Invoice Number</h3>
            <p class="mt-1 text-sm text-gray-500">Matching an Invoice Maker number links this transaction as a payment or related document.</p>
            <form method="POST" action="{{ route('transactions.update-invoice', $transaction) }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label for="tx-invoice-input" class="mb-1 block text-sm font-medium text-gray-700">Invoice number</label>
                    <input type="text" id="tx-invoice-input" name="invoice" maxlength="50" required
                           value="{{ old('invoice', $transaction->invoice) }}"
                           data-testid="tx-invoice-input"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    @error('invoice')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="invoiceModalOpen = false"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" data-testid="tx-invoice-save"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Primary Info Cards --}}
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3 lg:gap-4">
        {{-- Summary Card --}}
        <div class="overflow-hidden rounded-xl border border-blue-100 bg-white shadow-sm">
            <div class="h-1.5 w-full bg-blue-600"></div>
            <div class="px-3 pb-1 pt-3 sm:px-4 sm:pt-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[10px] font-medium tracking-wider text-gray-500 uppercase sm:text-xs">Grand Total</div>
                        <div class="mt-0.5 min-w-0 sm:mt-1">
                            <div class="text-[10px] font-semibold text-blue-600 sm:text-xs">IDR</div>
                            <div class="{{ $grandTotalHeroClass }} tabular-nums break-all text-blue-700">{{ $grandTotalFormatted }}</div>
                        </div>
                    </div>
                    <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold sm:hidden {{ $status['color'] }}">{{ $status['label'] }}</span>
                </div>
            </div>
            <div class="space-y-2 px-3 pb-3 pt-1 sm:space-y-3 sm:px-4 sm:pb-4 sm:pt-2">
                <div class="hidden items-center justify-between rounded-lg border border-dashed bg-gray-50 px-3 py-2 sm:flex">
                    <div class="text-sm font-medium">Status</div>
                    <span class="inline-flex items-center rounded-full px-3 py-0.5 text-xs font-semibold {{ $status['color'] }}">{{ $status['label'] }}</span>
                </div>
                <div class="grid grid-cols-2 gap-x-3 gap-y-1.5 sm:block sm:space-y-1.5">
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
                    <div class="col-span-2 flex justify-between text-sm">
                        <span class="flex items-center gap-1.5 text-gray-500">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 8V3a2 2 0 012-2z" transform="translate(0)"/></svg> Submit Source
                        </span>
                        @if((int) $transaction->submit_type === \App\Models\Transaction::SUBMIT_TYPE_JUBELIO)
                            <span class="inline-flex items-center gap-1 rounded-md border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-xs font-bold text-amber-500">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> cron jubelio
                            </span>
                        @elseif((int) $transaction->submit_type === \App\Models\Transaction::SUBMIT_TYPE_MANUAL)
                            <span class="inline-flex items-center gap-1 rounded-md border border-blue-500/20 bg-blue-500/10 px-2 py-0.5 text-xs font-bold text-blue-500">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg> aria submit
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">submit type {{ (int) $transaction->submit_type }}</span>
                        @endif
                    </div>
                    @if($transaction->user)
                    <div class="col-span-2 flex justify-between text-sm sm:col-span-1">
                        <span class="flex items-center gap-1.5 text-gray-500">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> Created By
                        </span>
                        <span class="font-medium underline decoration-blue-500/30">{{ $transaction->user->name }}</span>
                    </div>
                    @endif
                    @if($jubelioSync['show_ui'] ?? false)
                    <div class="col-span-2 flex justify-between pt-2 text-sm">
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

        {{-- Mobile: combined parties --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:hidden">
            <div class="border-b border-gray-100 bg-gray-50/50 px-3 py-2 text-[10px] font-bold tracking-wider text-gray-500 uppercase">Contacts</div>
            <div class="divide-y divide-gray-100">
                @foreach([
                    ['party' => $transaction->sender, 'label' => $config['sender_label'], 'direction' => 'From', 'accent' => 'blue', 'emptyText' => 'No sender info'],
                    ['party' => $transaction->receiver, 'label' => $config['receiver_label'], 'direction' => 'To', 'accent' => 'green', 'emptyText' => 'No receiver info'],
                ] as $contact)
                <div class="px-3 py-2.5">
                    <div class="text-[10px] font-semibold tracking-wide text-gray-400 uppercase">{{ $contact['label'] }} ({{ $contact['direction'] }})</div>
                    @if($contact['party'])
                        @php $contactUrl = route('addrbook.type.show', ['type' => $contact['party']->type_slug, 'addrbook' => $contact['party']->id]); @endphp
                        <div class="mt-1 flex items-center gap-2">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-{{ $contact['accent'] }}-100 text-xs font-bold text-{{ $contact['accent'] }}-600">
                                {{ mb_substr($contact['party']->name, 0, 1) }}
                            </div>
                            <div class="min-w-0">
                                <a href="{{ $contactUrl }}" class="block truncate text-sm font-semibold text-blue-600 hover:underline">{{ $contact['party']->name }}</a>
                                <p class="truncate text-[11px] text-gray-500">{{ $contact['party']->type_name }} · ID {{ $contact['party']->id }}</p>
                            </div>
                        </div>
                    @else
                        <p class="mt-1 text-xs italic text-gray-400">{{ $contact['emptyText'] }}</p>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        {{-- Desktop: separate party cards --}}
        <div class="hidden lg:contents">
            @include('transactions.partials.show-party', [
                'party' => $transaction->sender,
                'label' => $config['sender_label'],
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

            @include('transactions.partials.show-party', [
                'party' => $transaction->receiver,
                'label' => $config['receiver_label'],
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
    </div>

    @include('transactions.partials.jubelio-sync', ['transaction' => $transaction, 'jubelioSync' => $jubelioSync ?? []])

    @if($invoiceSettlement)
    <div class="print:hidden">
        <div class="mb-2 flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-700">Invoice Maker</h2>
            @if($can['invoice_maker_view'] ?? false)
            <a href="{{ route('invoice-maker.show', $invoiceSettlement['invoice']) }}"
               class="text-sm font-medium text-blue-700 hover:underline">
                {{ $invoiceSettlement['invoice']->number }}
            </a>
            @else
            <span class="text-sm font-medium text-gray-700">{{ $invoiceSettlement['invoice']->number }}</span>
            @endif
        </div>
        @include('invoice-maker.partials.settlement-card', [
            'settlement' => $invoiceSettlement,
            'canEdit' => $can['invoice_maker_edit'] ?? false,
        ])
    </div>
    @endif

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
            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <button type="button"
                        @click="copyItemsTable()"
                        data-testid="copy-items-table"
                        title="Copy items table for Excel"
                        class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span x-text="copyFeedback ? 'Copied!' : 'Copy items'"></span>
                </button>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-full border border-dashed bg-gray-50 px-4 py-2">
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
                <label class="flex cursor-pointer items-center gap-2 text-xs font-bold">
                    <input type="checkbox" x-model="showName" class="h-4 w-4 rounded border-gray-300"> Name
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-xs font-bold">
                    <input type="checkbox" x-model="showDescription" class="h-4 w-4 rounded border-gray-300"> Desc
                </label>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table x-ref="itemsTable" class="w-full min-w-[720px] text-sm">
                <thead class="border-y bg-gray-50 text-[10px] font-bold tracking-wider text-gray-500 uppercase">
                    <tr>
                        <th class="px-3 py-2.5 text-center font-black" data-copy-col="image" x-show="showImage">Img</th>
                        @foreach([
                            ['col' => 'barcode', 'label' => 'Barcode', 'align' => 'text-left'],
                            ['col' => 'sku', 'label' => 'SKU', 'align' => 'text-left'],
                            ['col' => 'name', 'label' => 'Item Name', 'align' => 'text-left min-w-[12rem]'],
                            ['col' => 'desc', 'label' => 'Desc', 'align' => 'text-left min-w-[10rem]'],
                            ['col' => 'qty', 'label' => 'Qty', 'align' => 'text-center'],
                            ['col' => 'price', 'label' => 'Price', 'align' => 'text-right'],
                            ['col' => 'disc', 'label' => 'Disc(%)', 'align' => 'text-right'],
                            ['col' => 'subtotal', 'label' => 'Subtotal', 'align' => 'text-right'],
                        ] as $sortCol)
                        <th class="px-3 py-2.5 font-black {{ $sortCol['align'] }}"
                            data-copy-col="{{ $sortCol['col'] }}"
                            @if(in_array($sortCol['col'], ['barcode', 'sku', 'name', 'desc'], true)) x-show="{{ match($sortCol['col']) { 'barcode' => 'showBarcode', 'sku' => 'showSku', 'name' => 'showName', 'desc' => 'showDescription', default => 'true' } }}" @endif>
                            <button type="button"
                                    @click="sortItems('{{ $sortCol['col'] }}')"
                                    class="inline-flex w-full items-center gap-1 hover:text-gray-900 {{ str_contains($sortCol['align'], 'text-right') ? 'justify-end' : (str_contains($sortCol['align'], 'text-center') ? 'justify-center' : 'justify-start') }}">
                                {{ $sortCol['label'] }}
                                <span x-show="sortCol === '{{ $sortCol['col'] }}'" class="text-blue-600" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                            </button>
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($transaction->details as $detail)
                    @php $item = $detail->item; @endphp
                    <tr class="group transition-colors hover:bg-gray-50">
                        <td class="px-3 py-2.5 text-center align-middle" data-copy-col="image" x-show="showImage">
                            <div class="relative mx-auto flex h-12 w-12 items-center justify-center overflow-hidden rounded border bg-white shadow-sm transition-transform duration-200 group-hover:scale-105">
                                @if($item?->image_url)
                                    <img src="{{ $item->image_url }}" alt="{{ $item?->getItemName() }}" class="h-full w-full object-cover">
                                @else
                                    <svg class="h-6 w-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                @endif
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 align-middle font-mono text-xs" data-copy-col="barcode" data-sort-value="{{ $item?->id ?? 0 }}" x-show="showBarcode">
                            <a href="{{ $item ? route('items.show', $item->id) : '#' }}" class="text-blue-600 hover:underline">{{ $item?->id }}</a>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 align-middle font-mono text-xs italic text-gray-500" data-copy-col="sku" data-sort-value="{{ $item?->code ?: '' }}" x-show="showSku">{{ $item?->code ?: '-' }}</td>
                        <td class="px-3 py-2.5 align-middle" data-copy-col="name" data-sort-value="{{ $item?->getItemName() ?: '' }}" x-show="showName">
                            <div class="font-bold text-gray-900">{{ $item?->getItemName() }}</div>
                            @if($item?->code)
                            <div class="mt-0.5 font-mono text-[10px] leading-tight text-gray-500">{{ $item?->code }}</div>
                            @endif
                            @if($detail->notes)
                            <div class="mt-1 text-xs italic text-gray-500">📝 {{ $detail->notes }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 align-middle text-gray-600" data-copy-col="desc" data-sort-value="{{ $item?->description ?: '' }}" x-show="showDescription">{{ $item?->description ?: '-' }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-center align-middle font-black tabular-nums" data-copy-col="qty" data-sort-value="{{ $detail->quantity }}">{{ $detail->quantity }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right align-middle font-medium tabular-nums" data-copy-col="price" data-sort-value="{{ $detail->price }}">{{ $fmt($detail->price) }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right align-middle tabular-nums" data-copy-col="disc" data-sort-value="{{ (float) $detail->discount }}">
                            @if($detail->discount > 0)
                                <span class="inline-flex h-5 items-center rounded-md border border-dashed border-red-300 bg-red-50 px-1.5 text-[10px] font-bold text-red-600">-{{ format_amount((float) $detail->discount) }}%</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right align-middle font-black text-blue-700 tabular-nums" data-copy-col="subtotal" data-sort-value="{{ $detail->total }}">{{ $fmt($detail->total) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Footer Totals & Summary --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        {{-- Notes Card --}}
        <div class="flex h-full flex-col rounded-xl bg-white shadow-sm">
            <div class="flex items-center justify-between border-b bg-gray-50/50 p-6 py-4">
                <div class="flex items-center gap-2 text-sm font-bold">
                    <svg class="h-4 w-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Description
                </div>
                @if($can['edit_transaction'] ?? false)
                    <button type="button"
                            data-testid="edit-tx-show-note"
                            @click="openNoteEdit()"
                            class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 print:hidden">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        Edit
                    </button>
                @endif
            </div>
            <div class="flex-1 p-6 pt-4">
                <p x-show="noteText" class="border-l-2 border-blue-200 py-1 pl-4 text-sm leading-relaxed whitespace-pre-line italic text-gray-500" x-text="noteText"></p>
                <div x-show="!noteText" class="flex h-full flex-col items-center justify-center py-4 text-gray-400 opacity-30">
                        <svg class="mb-1 h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs italic">No description added</p>
                    </div>
            </div>
        </div>

        {{-- Financial Summary Card --}}
        <div class="rounded-xl border border-blue-200 bg-blue-50/20 shadow-sm">
            <div class="space-y-3 p-6 tabular-nums">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Subtotal</span>
                    <span class="font-bold">{{ $fmt($transaction->displaySummarySubtotal()) }}</span>
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
                    <span class="text-gray-500">PPN / Tax</span>
                    <div class="flex items-center gap-2">
                        <span class="font-bold" x-text="ppnDisplay"></span>
                        @if($canEditPpn ?? false)
                        <button type="button"
                                data-testid="edit-tx-show-ppn"
                                @click="openPpnEdit()"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50 print:hidden">
                            Edit
                        </button>
                        @endif
                    </div>
                </div>
                <div x-show="ppnRecord" x-cloak class="flex items-center justify-between text-xs text-gray-500">
                    <span>Tax DPP</span>
                    <span x-text="ppnDppDisplay"></span>
                </div>
                <div x-show="pphRecord" x-cloak class="flex items-center justify-between text-xs text-gray-500">
                    <span>PPh withheld</span>
                    <span x-text="pphDisplay"></span>
                </div>
                <div class="pt-2">
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-blue-600 p-4 text-white shadow-lg shadow-blue-500/20">
                        <div class="flex min-w-0 flex-shrink-0 flex-col">
                            <span class="text-[10px] font-black tracking-widest text-blue-100/70 uppercase">Grand Total</span>
                            <span class="text-xs font-medium italic text-blue-100">Net Amount Payable</span>
                        </div>
                        <span class="min-w-0 break-all text-right tabular-nums {{ $grandTotalCompactClass }}">IDR {{ $grandTotalFormatted }}</span>
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

@push('scripts')
<script>
function transactionShowPage(transactionId, initialNote, canEditNote, canEditPpn, initialPpn, initialPpnDpp, initialPph, ppnRate, pphRate, transactionTotal) {
    const storageKey = 'aria-transaction-show-view';
    const defaults = { showImage: true, showBarcode: true, showSku: false, showName: true, showDescription: false };
    let saved = {};

    try {
        saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
    } catch (e) {
        saved = {};
    }

    return {
        showImage: typeof saved.showImage === 'boolean' ? saved.showImage : defaults.showImage,
        showBarcode: typeof saved.showBarcode === 'boolean' ? saved.showBarcode : defaults.showBarcode,
        showSku: typeof saved.showSku === 'boolean' ? saved.showSku : defaults.showSku,
        showName: typeof saved.showName === 'boolean' ? saved.showName : defaults.showName,
        showDescription: typeof saved.showDescription === 'boolean' ? saved.showDescription : defaults.showDescription,
        copyFeedback: false,
        copyFeedbackTimer: null,
        sortCol: null,
        sortDir: 'asc',
        waOpen: false,
        deleteConfirmOpen: false,
        printMenuOpen: false,
        invoiceMenuOpen: false,
        invoiceModalOpen: {{ $errors->has('invoice') ? 'true' : 'false' }},
        noteText: initialNote || '',
        noteDraft: initialNote || '',
        noteModalOpen: false,
        noteSaving: false,
        noteError: '',
        canEditNote: !!canEditNote,
        canEditPpn: !!canEditPpn,
        ppnRate: Number(ppnRate || 11),
        pphRate: Number(pphRate || 10),
        transactionTotal: Number(transactionTotal || 0),
        ppnManual: Number(initialPpn || 0) > 0,
        ppnAmount: Number(initialPpn || 0),
        ppnDppAmount: initialPpnDpp !== null ? Number(initialPpnDpp) : null,
        pphAmount: initialPph !== null ? Number(initialPph) : null,
        ppnRecord: Number(initialPpn || 0) > 0,
        pphRecord: Number(initialPph || 0) > 0,
        ppnDisplay: formatAmountId(Number(initialPpn || 0)),
        ppnDppDisplay: initialPpnDpp !== null ? formatAmountId(Number(initialPpnDpp)) : '-',
        pphDisplay: initialPph !== null ? formatAmountId(Number(initialPph)) : '-',
        ppnModalOpen: false,
        ppnDppDraft: initialPpnDpp !== null ? Number(initialPpnDpp) : null,
        ppnTaxDraft: Number(initialPpn || 0),
        pphTaxDraft: initialPph !== null ? Number(initialPph) : null,
        ppnSaving: false,
        ppnError: '',
        openNoteEdit() {
            if (!this.canEditNote) {
                return;
            }
            this.noteDraft = this.noteText;
            this.noteError = '';
            this.noteModalOpen = true;
            this.$nextTick(() => this.$refs.noteTextarea?.focus());
        },
        async saveNote() {
            if (!this.canEditNote || this.noteSaving) {
                return;
            }
            this.noteSaving = true;
            this.noteError = '';
            try {
                const res = await fetch(`/transactions/${transactionId}/note`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ note: this.noteDraft }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.noteError = data.message || (data.errors?.note?.[0] ?? 'Failed to save note.');
                    return;
                }
                this.noteText = data.note || '';
                this.noteModalOpen = false;
            } catch (e) {
                this.noteError = 'Failed to save note.';
            } finally {
                this.noteSaving = false;
            }
        },
        openPpnEdit() {
            if (!this.canEditPpn) {
                return;
            }
            this.ppnRecord = this.ppnAmount > 0;
            this.pphRecord = this.pphAmount !== null && this.pphAmount > 0;
            this.ppnManual = this.ppnAmount > 0;
            this.ppnDppDraft = this.ppnDppAmount;
            this.ppnTaxDraft = this.ppnAmount;
            this.pphTaxDraft = this.pphAmount;
            this.ppnError = '';
            this.ppnModalOpen = true;
            if (this.ppnRecord && ! this.ppnManual) {
                this.syncTaxDraftFromTotal();
            }
            this.$nextTick(() => this.$refs.ppnDppInput?.focus());
        },
        onPpnRecordToggle() {
            if (!this.ppnRecord) {
                this.ppnDppDraft = null;
                this.ppnTaxDraft = null;
                this.pphTaxDraft = null;
                this.pphRecord = false;
                this.ppnManual = false;
                return;
            }
            this.ppnManual = false;
            this.syncTaxDraftFromTotal();
        },
        onPphRecordToggle() {
            if (!this.pphRecord) {
                this.pphTaxDraft = null;
            }
            if (!this.ppnManual) {
                this.syncTaxDraftFromTotal();
            }
        },
        syncTaxDraftFromTotal() {
            const payment = Number(this.transactionTotal || 0);
            if (payment < 0.01) {
                return;
            }
            const ppnRate = this.ppnRate / 100;
            const pphRate = this.pphRecord ? (this.pphRate / 100) : 0;
            const divisor = 1 + ppnRate - pphRate;
            this.ppnDppDraft = Math.round(payment / divisor * 100) / 100;
            this.ppnTaxDraft = Math.round(this.ppnDppDraft * ppnRate * 100) / 100;
            this.pphTaxDraft = this.pphRecord ? Math.round(this.ppnDppDraft * pphRate * 100) / 100 : null;
        },
        async savePpn() {
            if (!this.canEditPpn || this.ppnSaving) {
                return;
            }
            if (this.ppnRecord && ! this.ppnManual) {
                this.syncTaxDraftFromTotal();
            }
            if (this.ppnRecord && (!(Number(this.ppnDppDraft) >= 0.01) || !(Number(this.ppnTaxDraft) >= 0.01))) {
                this.ppnError = 'DPP and PPN are required when recording PPN.';
                return;
            }
            if (this.ppnRecord && this.pphRecord && !(Number(this.pphTaxDraft) >= 0.01)) {
                this.ppnError = 'PPh is required when withholding is enabled.';
                return;
            }
            this.ppnSaving = true;
            this.ppnError = '';
            try {
                const res = await fetch(`/transactions/${transactionId}/ppn`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        record_ppn: !!this.ppnRecord,
                        record_pph: !!this.pphRecord,
                        ppn_dpp: this.ppnRecord ? Number(this.ppnDppDraft || 0) : null,
                        ppn: this.ppnRecord ? Number(this.ppnTaxDraft || 0) : null,
                        pph: this.ppnRecord && this.pphRecord ? Number(this.pphTaxDraft || 0) : null,
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.ppnError = data.message || (data.errors?.ppn?.[0] ?? 'Failed to save PPN.');
                    return;
                }
                this.ppnAmount = Number(data.ppn || 0);
                this.ppnDppAmount = data.ppn_dpp !== null ? Number(data.ppn_dpp) : null;
                this.pphAmount = data.pph !== null ? Number(data.pph) : null;
                this.ppnRecord = !!data.record_ppn;
                this.pphRecord = !!data.record_pph;
                this.ppnDisplay = data.display_ppn || formatAmountId(this.ppnAmount);
                this.ppnDppDisplay = data.display_dpp || '-';
                this.pphDisplay = data.display_pph || '-';
                this.ppnModalOpen = false;
            } catch (e) {
                this.ppnError = 'Failed to save PPN.';
            } finally {
                this.ppnSaving = false;
            }
        },
        init() {
            this.$watch('showImage', () => this.persistViewPrefs());
            this.$watch('showBarcode', () => this.persistViewPrefs());
            this.$watch('showSku', () => this.persistViewPrefs());
            this.$watch('showName', () => this.persistViewPrefs());
            this.$watch('showDescription', () => this.persistViewPrefs());
        },
        persistViewPrefs() {
            localStorage.setItem(storageKey, JSON.stringify({
                showImage: this.showImage,
                showBarcode: this.showBarcode,
                showSku: this.showSku,
                showName: this.showName,
                showDescription: this.showDescription,
            }));
        },
        itemViewQuery() {
            const params = new URLSearchParams();
            params.set('image', this.showImage ? '1' : '0');
            params.set('barcode', this.showBarcode ? '1' : '0');
            params.set('sku', this.showSku ? '1' : '0');
            params.set('name', this.showName ? '1' : '0');
            params.set('desc', this.showDescription ? '1' : '0');

            return params.toString();
        },
        showCopyFeedback() {
            this.copyFeedback = true;
            clearTimeout(this.copyFeedbackTimer);
            this.copyFeedbackTimer = setTimeout(() => {
                this.copyFeedback = false;
            }, 2000);
        },
        isCopyColumnVisible(col) {
            if (col === 'image') return this.showImage;
            if (col === 'barcode') return this.showBarcode;
            if (col === 'sku') return this.showSku;
            if (col === 'name') return this.showName;
            if (col === 'desc') return this.showDescription;

            return true;
        },
        sortValueForRow(row, col) {
            const cell = row.querySelector('[data-copy-col="' + col + '"]');
            if (!cell) {
                return '';
            }

            return cell.dataset.sortValue ?? '';
        },
        compareSortValues(a, b) {
            const aNum = Number(a);
            const bNum = Number(b);
            const aIsNum = a !== '' && Number.isFinite(aNum);
            const bIsNum = b !== '' && Number.isFinite(bNum);

            if (aIsNum && bIsNum) {
                return aNum - bNum;
            }

            return String(a).localeCompare(String(b), undefined, { sensitivity: 'base', numeric: true });
        },
        sortItems(col) {
            const table = this.$refs.itemsTable;
            if (!table) {
                return;
            }

            const tbody = table.querySelector('tbody');
            if (!tbody) {
                return;
            }

            if (this.sortCol === col) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortCol = col;
                this.sortDir = 'asc';
            }

            const dir = this.sortDir === 'asc' ? 1 : -1;
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((rowA, rowB) => this.compareSortValues(
                this.sortValueForRow(rowA, col),
                this.sortValueForRow(rowB, col),
            ) * dir);

            rows.forEach((row) => tbody.appendChild(row));
        },
        cellCopyValue(cell) {
            const img = cell.querySelector('img');
            if (img) {
                return (img.getAttribute('alt') || img.getAttribute('src') || '').trim();
            }

            const link = cell.querySelector('a');
            if (link) {
                return link.textContent.trim();
            }

            return cell.innerText.replace(/\s+/g, ' ').trim();
        },
        tableNodeToTsv(table) {
            const rows = [];

            table.querySelectorAll('thead tr, tbody tr').forEach((row) => {
                const values = [];

                row.querySelectorAll('[data-copy-col]').forEach((cell) => {
                    if (!this.isCopyColumnVisible(cell.dataset.copyCol)) {
                        return;
                    }

                    values.push(this.cellCopyValue(cell));
                });

                if (values.length) {
                    rows.push(values.join('\t'));
                }
            });

            return rows.join('\n');
        },
        async copyItemsTable() {
            const table = this.$refs.itemsTable;
            if (!table) {
                return;
            }

            const clone = table.cloneNode(true);
            clone.querySelectorAll('[data-copy-col]').forEach((cell) => {
                if (!this.isCopyColumnVisible(cell.dataset.copyCol)) {
                    cell.remove();
                }
            });

            const plain = this.tableNodeToTsv(clone);
            const html = clone.outerHTML;

            try {
                if (window.ClipboardItem && navigator.clipboard?.write) {
                    await navigator.clipboard.write([
                        new ClipboardItem({
                            'text/plain': new Blob([plain], { type: 'text/plain' }),
                            'text/html': new Blob([html], { type: 'text/html' }),
                        }),
                    ]);
                } else {
                    await navigator.clipboard.writeText(plain);
                }

                this.showCopyFeedback();
            } catch (e) {
                try {
                    await navigator.clipboard.writeText(plain);
                    this.showCopyFeedback();
                } catch (fallbackError) {
                    console.error('Failed to copy items table', fallbackError);
                }
            }
        },
    };
}
</script>
@endpush
