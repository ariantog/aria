@php
    $returnCreateUrl = (function () use ($can, $transaction) {
        if (! ($can['return_draft'] ?? false)) {
            return null;
        }

        $returnType = app(\App\Services\TransactionReturnDraftService::class)->targetTypeSlug($transaction);

        return $returnType
            ? route('transactions.create', ['type' => $returnType, 'from' => $transaction->id])
            : null;
    })();
@endphp

<div class="-mx-1 flex w-full items-center gap-1.5 overflow-x-auto pb-0.5 sm:mx-0 sm:w-auto sm:flex-wrap sm:overflow-visible sm:gap-2">
    {{-- Print --}}
    <div class="relative shrink-0" x-data="{ open: false }">
        <button type="button" @click="open = !open" title="Print"
                class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:px-3">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            <span class="hidden sm:inline">Print</span>
            <svg class="hidden h-3.5 w-3.5 text-gray-400 sm:inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="open" x-cloak @click.away="open = false"
             class="absolute right-0 top-full z-30 mt-1 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
            <a href="{{ route('transactions.receipt', $transaction->id) }}" target="_blank"
               class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Print POS
            </a>
            <a href="{{ route('transactions.print', $transaction->id) }}" target="_blank"
               class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print Invoice
            </a>
        </div>
    </div>

    {{-- Invoice / PDF --}}
    <div class="relative shrink-0" x-data="{ open: false }">
        <button type="button" @click="open = !open" title="Invoice"
                class="inline-flex items-center gap-1.5 rounded-md border border-blue-300 bg-blue-50 px-2.5 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 sm:px-3">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            <span class="hidden sm:inline">Invoice</span>
            <svg class="hidden h-3.5 w-3.5 text-blue-400 sm:inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="open" x-cloak @click.away="open = false"
             class="absolute right-0 top-full z-30 mt-1 w-52 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
            @if($hasInvoicePdf)
            <a href="{{ $invoicePdfUrl }}" target="_blank" rel="noopener"
               class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                View PDF
            </a>
            <form method="POST" action="{{ route('transactions.pdf.store', $transaction->id) }}">
                @csrf
                <button type="submit" data-testid="regenerate-pdf-button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Regenerate PDF
                </button>
            </form>
            @else
            <form method="POST" action="{{ route('transactions.pdf.store', $transaction->id) }}">
                @csrf
                <button type="submit"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Save to PDF
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- WhatsApp --}}
    <button type="button" @click="waOpen = true" title="WhatsApp"
            class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-green-300 bg-green-50 px-2.5 py-2 text-sm font-medium text-green-700 hover:bg-green-100 sm:px-3">
        <svg class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        <span class="hidden sm:inline">WhatsApp</span>
    </button>

    @if($returnCreateUrl ?? false)
    <a href="{{ $returnCreateUrl }}" data-testid="return-transaction-button" title="Return"
       class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-orange-300 bg-orange-50 px-2.5 py-2 text-sm font-medium text-orange-700 hover:bg-orange-100 sm:px-3">
        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
        <span class="hidden sm:inline">Return</span>
    </a>
    @endif

    @if($can['delete_transaction'] ?? false)
    <button type="button" @click="deleteConfirmOpen = true" title="Hapus"
            data-testid="delete-transaction-button"
            class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-red-300 bg-red-50 px-2.5 py-2 text-sm font-medium text-red-700 hover:bg-red-100 sm:px-3">
        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        <span class="hidden sm:inline">Hapus</span>
    </button>
    @endif
</div>
