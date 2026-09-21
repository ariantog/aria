@php
    $invoiceLinked = $invoiceLinked ?? null;
    $linked = $invoiceLinked['linked'] ?? collect();
    $party = $invoiceLinked['party'] ?? 'receiver';
    $fmt = fn ($n) => format_amount($n);
@endphp
@if($invoiceLinked && $linked->isNotEmpty())
<div class="print:hidden rounded-xl border border-gray-200 bg-white shadow-sm"
     data-testid="invoice-linked-transactions">
    <div class="border-b border-gray-100 px-4 py-3 sm:px-5">
        <h2 class="text-sm font-semibold text-gray-900">{{ $invoiceLinked['title'] ?? 'Linked transactions' }}</h2>
        <p class="mt-0.5 text-xs text-gray-500">Other completed transactions with invoice #{{ $transaction->invoice }}.</p>
    </div>
    <div class="px-4 py-3 sm:px-5">
        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-100">
            @foreach($linked as $linkedTx)
            <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                <a href="{{ route('transactions.show', $linkedTx) }}" class="font-medium text-blue-700 hover:underline">
                    #{{ $linkedTx->id }}
                    <span class="font-normal text-gray-500">{{ $linkedTx->{$party}?->name }}</span>
                </a>
                <span class="font-mono tabular-nums text-gray-900">{{ $fmt($linkedTx->displayGrandTotal()) }}</span>
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endif
