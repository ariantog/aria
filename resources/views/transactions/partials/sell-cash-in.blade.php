@php
    $sellCashIn = $sellCashIn ?? null;
    $transaction = $transaction ?? null;
    $canCreate = (bool) ($sellCashIn['can_create'] ?? false);
    $banks = $sellCashIn['banks'] ?? collect();
    $defaultAccount = $sellCashIn['default_account'] ?? null;
    $linked = $sellCashIn['linked'] ?? collect();
    $defaultAmount = (float) ($sellCashIn['default_amount'] ?? 0);
    $defaultDate = $sellCashIn['default_date'] ?? now()->toDateString();
    $minDate = $sellCashIn['min_date'] ?? '';
    $hasCashInErrors = $errors->has('amount') || $errors->has('account_id') || $errors->has('date');
    $fmt = fn ($n) => format_amount($n);
@endphp
@if($sellCashIn && $transaction && ($canCreate || $linked->isNotEmpty()))
<div class="print:hidden rounded-xl border border-gray-200 bg-white shadow-sm"
     data-testid="sell-cash-in-card"
     x-data="sellCashInCard({
        enabled: {{ $hasCashInErrors ? 'true' : 'false' }},
        amount: {{ $hasCashInErrors ? (old('amount') !== null ? (float) old('amount') : $defaultAmount) : $defaultAmount }},
        accountId: @js((string) old('account_id', $defaultAccount['id'] ?? '')),
        date: @js(old('date', $defaultDate)),
        minDate: @js($minDate),
     })">
    <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
        <div>
            <h2 class="text-sm font-semibold text-gray-900">Cash In</h2>
            <p class="mt-0.5 text-xs text-gray-500">Record payment from {{ $transaction->receiver?->name ?: 'the receiver' }} with the same invoice.</p>
        </div>
        @if($canCreate)
        <label class="relative inline-flex cursor-pointer items-center" title="Create cash in">
            <input type="checkbox" x-model="enabled" data-testid="sell-cash-in-switch"
                   class="peer sr-only">
            <span class="h-6 w-11 rounded-full bg-gray-300 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white"></span>
        </label>
        @endif
    </div>

    @if($linked->isNotEmpty())
    <div class="border-b border-gray-100 px-4 py-3 sm:px-5">
        <h3 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Linked cash-in</h3>
        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-100">
            @foreach($linked as $cashIn)
            <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                <a href="{{ route('transactions.show', $cashIn) }}" class="font-medium text-blue-700 hover:underline">
                    #{{ $cashIn->id }}
                    <span class="font-normal text-gray-500">{{ $cashIn->receiver?->name }}</span>
                </a>
                <span class="font-mono tabular-nums text-gray-900">{{ $fmt($cashIn->displayGrandTotal()) }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if($canCreate)
    <form method="POST" action="{{ route('transactions.sell-cash-in.store', $transaction) }}"
          x-show="enabled" x-cloak class="space-y-4 px-4 py-4 sm:px-5">
        @csrf
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label for="sell-cash-in-date" class="mb-1 block text-sm font-medium text-gray-700">Date</label>
                <input type="date" id="sell-cash-in-date" name="date" x-model="date"
                       min="{{ $minDate }}"
                       data-testid="sell-cash-in-date"
                       class="w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                       :class="!dateValid() ? 'border-red-400 bg-red-50' : 'border-gray-300'">
                @error('date')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="sell-cash-in-amount" class="mb-1 block text-sm font-medium text-gray-700">Amount (Rp)</label>
                <input type="number" id="sell-cash-in-amount" name="amount" min="0.01" step="any"
                       x-model.number="amount"
                       data-testid="sell-cash-in-amount"
                       class="w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                       :class="!amountValid() ? 'border-red-400 bg-red-50' : 'border-gray-300'">
                @error('amount')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="sell-cash-in-bank" class="mb-1 block text-sm font-medium text-gray-700">Bank</label>
                <select id="sell-cash-in-bank" name="account_id" x-model="accountId"
                        data-testid="sell-cash-in-bank"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">Select bank account…</option>
                    @foreach($banks as $bank)
                    <option value="{{ $bank->id }}" @selected((string) ($defaultAccount['id'] ?? '') === (string) $bank->id)>{{ $bank->name }}</option>
                    @endforeach
                </select>
                @error('account_id')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <div class="flex items-center justify-end gap-3">
            <p x-show="!canSubmit()" x-cloak class="mr-auto text-xs text-gray-400">
                Choose a date, bank, and amount to create cash in.
            </p>
            <button type="submit" :disabled="!canSubmit()"
                    data-testid="sell-cash-in-submit"
                    class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500">
                Create Cash In
            </button>
        </div>
    </form>
    @endif
</div>
@endif

@push('scripts')
<script>
function sellCashInCard(initial) {
    return {
        enabled: !!initial.enabled,
        amount: Number(initial.amount || 0),
        accountId: initial.accountId ? String(initial.accountId) : '',
        date: initial.date || '',
        minDate: initial.minDate || '',
        dateValid() {
            if (!this.date) return false;
            if (this.minDate && this.date < this.minDate) return false;
            return true;
        },
        amountValid() {
            return Number(this.amount) >= 0.01;
        },
        accountValid() {
            return !!this.accountId;
        },
        canSubmit() {
            return this.enabled && this.dateValid() && this.amountValid() && this.accountValid();
        },
    };
}
</script>
@endpush
