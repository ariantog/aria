@php
    $settlement = $settlement ?? null;
    $canEdit = (bool) ($canEdit ?? false);
    $showTransactionLink = (bool) ($showTransactionLink ?? true);
@endphp
@if($settlement)
@php
    $invoice = $settlement['invoice'];
    $fmt = fn ($n) => format_currency($n);
@endphp
<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
     x-data="{
        invoiceAmount: {{ $settlement['invoice_amount'] }},
        subtotal: {{ (float) $invoice->subtotal }},
        cashIn: {{ $settlement['paid_total'] }},
        sell: {{ $settlement['sell_total'] }},
        discount: {{ $settlement['discount'] }},
        billedAmount() {
            return Math.max(0, Math.round((this.subtotal - Number(this.discount || 0)) * 100) / 100);
        },
        amountsMatch() {
            const invoice = this.billedAmount();
            return invoice > 0 && invoice === Number(this.cashIn) && invoice === Number(this.sell);
        },
        canWriteOff() {
            return Number(this.cashIn) > 0 && Number(this.cashIn) === Number(this.sell) && this.billedAmount() !== Number(this.cashIn);
        },
        useRemainingAsDiscount() {
            if (!this.canWriteOff()) {
                return;
            }
            this.discount = Math.max(0, Math.round((this.subtotal - Number(this.cashIn)) * 100) / 100);
        },
        formatRp(value) {
            return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(value);
        }
     }">
    <div class="mb-3 flex items-start justify-between gap-3">
        <div>
            <h3 class="font-semibold text-gray-900">Payment</h3>
            <p class="mt-0.5 text-xs text-gray-500">Paid when invoice, linked sell, and linked cash-in totals are the same. Transfers are ignored. Sender and receiver do not matter.</p>
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
            <dt class="text-gray-500">Discount</dt>
            <dd class="font-mono font-medium text-gray-900">{{ $fmt($settlement['discount']) }}</dd>
        </div>
        <div class="flex justify-between gap-3 border-t border-gray-100 pt-1.5">
            <dt class="font-semibold text-gray-900">Match</dt>
            <dd class="text-sm font-semibold" :class="amountsMatch() ? 'text-green-700' : 'text-amber-700'">
                <span x-show="amountsMatch()">Paid — amounts match</span>
                <span x-show="!amountsMatch()">Waiting for invoice = sell = cash-in</span>
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
    <div class="mt-4 space-y-3 border-t border-gray-100 pt-4">
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
        @if($settlement['is_paid'] && $invoice->paid_at)
        <p class="text-sm text-gray-600">
            Marked paid on {{ $invoice->paid_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
            @if($invoice->paidBy)
                by {{ $invoice->paidBy->name }}
            @endif
        </p>
        @endif
    </div>
    @endif
</div>
@endif
