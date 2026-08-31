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
        due: {{ $settlement['due'] }},
        paid: {{ $settlement['paid_total'] }},
        discount: {{ $settlement['discount'] }},
        remainingAmount() {
            const value = this.due - this.paid - Number(this.discount || 0);
            return Math.max(0, Math.round(value * 100) / 100);
        },
        canMarkPaid() {
            return this.remainingAmount() <= 0;
        },
        useRemainingAsDiscount() {
            this.discount = Math.max(0, Math.round((this.due - this.paid) * 100) / 100);
        },
        formatRp(value) {
            return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(value);
        }
     }">
    <div class="mb-3 flex items-start justify-between gap-3">
        <div>
            <h3 class="font-semibold text-gray-900">Payment</h3>
            <p class="mt-0.5 text-xs text-gray-500">Cash-in transactions with this invoice number count as payments. Extra discount writes off the remainder.</p>
        </div>
        @include('invoice-maker.partials.status-badge', [
            'status' => $settlement['status'],
            'label' => $settlement['status_label'],
        ])
    </div>

    <dl class="space-y-1.5 text-sm">
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500">Balance due</dt>
            <dd class="font-mono font-medium text-gray-900">{{ $fmt($settlement['due']) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500">Payments</dt>
            <dd class="font-mono font-medium text-gray-900">{{ $fmt($settlement['paid_total']) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500">Discount</dt>
            <dd class="font-mono font-medium text-gray-900">{{ $fmt($settlement['discount']) }}</dd>
        </div>
        <div class="flex justify-between gap-3 border-t border-gray-100 pt-1.5">
            <dt class="font-semibold text-gray-900">Remaining</dt>
            <dd class="font-mono text-lg font-bold text-gray-900">Rp <span x-text="formatRp(remainingAmount())">{{ number_format($settlement['remaining'], 0, ',', '.') }}</span></dd>
        </div>
    </dl>

    @if($settlement['payments']->isNotEmpty())
    <div class="mt-4">
        <h4 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Linked cash-in</h4>
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
                    @if($payment->sender)
                        <span class="block truncate text-xs text-gray-500">{{ $payment->sender->name }}</span>
                    @endif
                </div>
                <span class="shrink-0 font-mono text-gray-900">{{ $fmt(abs((float) $payment->total)) }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @else
    <p class="mt-4 text-sm text-gray-500">No cash-in transaction uses this invoice number yet.</p>
    @endif

    @if($settlement['related']->isNotEmpty())
    <div class="mt-4">
        <h4 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Other linked transactions</h4>
        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-100">
            @foreach($settlement['related'] as $related)
            <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                <div class="min-w-0">
                    @if($showTransactionLink)
                    <a href="{{ route('transactions.show', $related) }}" class="font-medium text-blue-700 hover:underline">#{{ $related->id }}</a>
                    @else
                    <span class="font-medium text-gray-900">#{{ $related->id }}</span>
                    @endif
                    <span class="text-gray-500"> · {{ $related->getTypeLabel() }} · {{ optional($related->date)->format('d/m/Y') }}</span>
                </div>
                <span class="shrink-0 font-mono text-gray-500">{{ $fmt(abs((float) $related->total)) }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if($canEdit)
    <div class="mt-4 space-y-3 border-t border-gray-100 pt-4">
        @if(! $settlement['is_paid'])
        <div class="space-y-3">
            <div>
                <label for="settlement-discount-{{ $invoice->id }}" class="mb-1 block text-sm font-medium text-gray-700">Additional discount</label>
                <div class="flex gap-2">
                    <input type="number" step="0.01" min="0"
                           id="settlement-discount-{{ $invoice->id }}"
                           data-testid="invoice-discount-input"
                           x-model.number="discount"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <button type="button" @click="useRemainingAsDiscount()"
                            data-testid="invoice-use-remaining-discount"
                            class="shrink-0 rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
                        Write off remainder
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500">Use this when the customer pays a bit less than the invoice. It does not create a transaction.</p>
                @error('discount_amount')
                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('invoice-maker.discount', $invoice) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="discount_amount" :value="discount">
                    <button type="submit" data-testid="invoice-save-discount"
                            class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Save discount
                    </button>
                </form>
                <form method="POST" action="{{ route('invoice-maker.mark-paid', $invoice) }}">
                    @csrf
                    <input type="hidden" name="discount_amount" :value="discount">
                    <button type="submit" data-testid="invoice-mark-paid" :disabled="!canMarkPaid()"
                            class="rounded-md bg-green-700 px-3 py-2 text-sm font-medium text-white hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-50">
                        Mark as paid
                    </button>
                </form>
            </div>
        </div>
        @else
        <div class="text-sm text-gray-600">
            Marked paid
            @if($invoice->paid_at)
                on {{ $invoice->paid_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
            @endif
            @if($invoice->paidBy)
                by {{ $invoice->paidBy->name }}
            @endif
        </div>
        <form method="POST" action="{{ route('invoice-maker.unmark-paid', $invoice) }}">
            @csrf
            <button type="submit" data-testid="invoice-unmark-paid"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Mark as unpaid
            </button>
        </form>
        @endif
    </div>
    @endif
</div>
@endif
