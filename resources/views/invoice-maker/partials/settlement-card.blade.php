@php
    $settlement = $settlement ?? null;
    $canEdit = (bool) ($canEdit ?? false);
    $showTransactionLink = (bool) ($showTransactionLink ?? true);
    $embedded = (bool) ($embedded ?? false);
    $amountsAlreadyMatch = (bool) ($settlement['amounts_match'] ?? false);
@endphp
@if($settlement)
@php
    $invoice = $settlement['invoice'];
    $fmt = fn ($n) => format_currency($n);
@endphp
<div @class([
        'rounded-xl border border-gray-200 bg-white p-4 shadow-sm' => ! $embedded,
        'pt-1' => $embedded,
    ])
     x-data="{
        invoiceAmount: {{ $settlement['invoice_amount'] }},
        subtotal: {{ (float) $invoice->subtotal }},
        cashIn: {{ $settlement['paid_total'] }},
        sell: {{ $settlement['sell_total'] }},
        returnTotal: {{ $settlement['return_total'] ?? 0 }},
        cashOut: {{ $settlement['cash_out_total'] ?? 0 }},
        credit: {{ $settlement['credit_total'] ?? $settlement['paid_total'] }},
        debit: {{ $settlement['debit_total'] ?? $settlement['sell_total'] }},
        discount: {{ $settlement['discount'] }},
        billedAmount() {
            return Math.max(0, Math.round((this.subtotal - Number(this.discount || 0)) * 100) / 100);
        },
        linkingComplete() {
            const credit = Math.round((Number(this.cashIn) + Number(this.returnTotal)) * 100) / 100;
            const debit = Math.round((Number(this.sell) + Number(this.cashOut)) * 100) / 100;
            return credit > 0 && credit === debit;
        },
        amountsMatch() {
            const invoice = this.billedAmount();
            const credit = Math.round((Number(this.cashIn) + Number(this.returnTotal)) * 100) / 100;
            const debit = Math.round((Number(this.sell) + Number(this.cashOut)) * 100) / 100;
            return invoice > 0 && this.linkingComplete() && invoice === credit && invoice === debit;
        },
        canWriteOff() {
            const credit = Math.round((Number(this.cashIn) + Number(this.returnTotal)) * 100) / 100;
            return this.linkingComplete() && this.billedAmount() !== credit;
        },
        useRemainingAsDiscount() {
            if (!this.canWriteOff()) {
                return;
            }
            const credit = Math.round((Number(this.cashIn) + Number(this.returnTotal)) * 100) / 100;
            this.discount = Math.max(0, Math.round((this.subtotal - credit) * 100) / 100);
        },
        formatRp(value) {
            return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(value);
        }
     }">
    <div class="mb-3 flex items-start justify-between gap-3">
        <div>
            <h3 class="font-semibold text-gray-900">Payment</h3>
            <p class="mt-0.5 text-xs text-gray-500">Paid when the invoice amount matches linked totals and (cash-in + return) equals (sell + cash-out). Transfers and buys are ignored. Sender and receiver do not matter.</p>
        </div>
        @include('invoice-maker.partials.status-badge', [
            'status' => $settlement['status'],
            'label' => $settlement['status_label'],
        ])
    </div>

    <dl class="space-y-1.5 text-sm">
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500">Invoice</dt>
            <dd class="font-mono font-medium text-gray-900">Rp <span x-text="formatRp(billedAmount())">{{ number_format($settlement['invoice_amount'], 0, ',', '.') }}</span></dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500">Sell</dt>
            <dd class="font-mono font-medium text-gray-900">{{ $fmt($settlement['sell_total']) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500">Cash-in</dt>
            <dd class="font-mono font-medium text-gray-900">{{ $fmt($settlement['paid_total']) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500">Return</dt>
            <dd class="font-mono font-medium text-gray-900">{{ $fmt($settlement['return_total'] ?? 0) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500">Cash-out</dt>
            <dd class="font-mono font-medium text-gray-900">{{ $fmt($settlement['cash_out_total'] ?? 0) }}</dd>
        </div>
        <div class="flex justify-between gap-3 border-t border-gray-100 pt-1.5">
            <dt class="text-gray-500">Credit (in + return)</dt>
            <dd class="font-mono font-medium text-gray-900">{{ $fmt($settlement['credit_total'] ?? $settlement['paid_total']) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500">Debit (sell + cash-out)</dt>
            <dd class="font-mono font-medium text-gray-900">{{ $fmt($settlement['debit_total'] ?? $settlement['sell_total']) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500">Discount</dt>
            <dd class="font-mono font-medium text-gray-900">{{ $fmt($settlement['discount']) }}</dd>
        </div>
        <div class="flex justify-between gap-3 border-t border-gray-100 pt-1.5">
            <dt class="font-semibold text-gray-900">Match</dt>
            <dd class="text-sm font-semibold" :class="amountsMatch() ? 'text-green-700' : 'text-amber-700'">
                <span x-show="amountsMatch()">Paid — amounts match</span>
                <span x-show="!amountsMatch()">Waiting for invoice = credit = debit and (cash-in + return) = (sell + cash-out)</span>
            </dd>
        </div>
    </dl>

    <div class="mt-4">
        <h4 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Linked sell</h4>
        @if($settlement['sells']->isNotEmpty())
        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-100">
            @foreach($settlement['sells'] as $sell)
            <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                <div class="min-w-0">
                    @if($showTransactionLink)
                    <a href="{{ route('transactions.show', $sell) }}" class="font-medium text-blue-700 hover:underline">#{{ $sell->id }}</a>
                    @else
                    <span class="font-medium text-gray-900">#{{ $sell->id }}</span>
                    @endif
                    <span class="text-gray-500"> · {{ optional($sell->date)->format('d/m/Y') }}</span>
                </div>
                <span class="shrink-0 font-mono text-gray-900">{{ $fmt(abs((float) $sell->total)) }}</span>
            </li>
            @endforeach
        </ul>
        @else
        <p class="text-sm text-gray-500">No sell transaction uses this invoice number yet.</p>
        @endif
    </div>

    @if(($settlement['returns'] ?? collect())->isNotEmpty())
    <div class="mt-4">
        <h4 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Linked return</h4>
        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-100">
            @foreach($settlement['returns'] as $returnTx)
            <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                <div class="min-w-0">
                    @if($showTransactionLink)
                    <a href="{{ route('transactions.show', $returnTx) }}" class="font-medium text-blue-700 hover:underline">#{{ $returnTx->id }}</a>
                    @else
                    <span class="font-medium text-gray-900">#{{ $returnTx->id }}</span>
                    @endif
                    <span class="text-gray-500"> · {{ optional($returnTx->date)->format('d/m/Y') }}</span>
                </div>
                <span class="shrink-0 font-mono text-gray-900">{{ $fmt(abs((float) $returnTx->total)) }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(($settlement['cash_outs'] ?? collect())->isNotEmpty())
    <div class="mt-4">
        <h4 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Linked cash-out</h4>
        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-100">
            @foreach($settlement['cash_outs'] as $cashOut)
            <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                <div class="min-w-0">
                    @if($showTransactionLink)
                    <a href="{{ route('transactions.show', $cashOut) }}" class="font-medium text-blue-700 hover:underline">#{{ $cashOut->id }}</a>
                    @else
                    <span class="font-medium text-gray-900">#{{ $cashOut->id }}</span>
                    @endif
                    <span class="text-gray-500"> · {{ optional($cashOut->date)->format('d/m/Y') }}</span>
                </div>
                <span class="shrink-0 font-mono text-gray-900">{{ $fmt(abs((float) $cashOut->total)) }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="mt-4">
        <h4 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Linked cash-in</h4>
        @if($settlement['payments']->isNotEmpty())
        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-100">
            @foreach($settlement['payments'] as $payment)
            <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                <div class="min-w-0">
                    @if($showTransactionLink)
                    <a href="{{ route('transactions.show', $payment) }}" class="font-medium text-blue-700 hover:underline">#{{ $payment->id }}</a>
                    @else
                    <span class="font-medium text-gray-900">#{{ $payment->id }}</span>
                    @endif
                    <span class="text-gray-500"> · {{ optional($payment->date)->format('d/m/Y') }}</span>
                </div>
                <span class="shrink-0 font-mono text-gray-900">{{ $fmt(abs((float) $payment->total)) }}</span>
            </li>
            @endforeach
        </ul>
        @else
        <p class="text-sm text-gray-500">No cash-in transaction uses this invoice number yet.</p>
        @endif
    </div>

    @if($canEdit)
        @if(! $amountsAlreadyMatch)
        <div class="mt-4 space-y-3 border-t border-gray-100 pt-4" x-show="!amountsMatch()" x-cloak>
            <div>
                <label for="settlement-discount-{{ $invoice->id }}" class="mb-1 block text-sm font-medium text-gray-700">Additional discount</label>
                <div class="flex gap-2">
                    <input type="number" step="0.01" min="0"
                           id="settlement-discount-{{ $invoice->id }}"
                           data-testid="invoice-discount-input"
                           x-model.number="discount"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <button type="button" @click="useRemainingAsDiscount()" :disabled="!canWriteOff()"
                            data-testid="invoice-use-remaining-discount"
                            class="shrink-0 rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">
                        Write off remainder
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500">Use this after sell and cash-in are already entered, when the customer paid a bit less than the invoice. Saving re-checks paid status.</p>
                @error('discount_amount')
                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <form method="POST" action="{{ route('invoice-maker.discount', $invoice) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="discount_amount" :value="discount">
                <button type="submit" data-testid="invoice-save-discount"
                        class="rounded-md bg-blue-700 px-3 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Save discount
                </button>
            </form>
        </div>
        @endif
        @if($settlement['is_paid'] && $invoice->paid_at)
        <p @class(['text-sm text-gray-600', 'mt-4 border-t border-gray-100 pt-4' => $amountsAlreadyMatch])>
            Marked paid on {{ $invoice->paid_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
            @if($invoice->paidBy)
                by {{ $invoice->paidBy->name }}
            @endif
        </p>
        @endif
    @endif
</div>
@endif
