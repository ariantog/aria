@php
    use App\Models\Addrbook;
    $sellCashIn = $sellCashIn ?? null;
    $transaction = $transaction ?? null;
    $invoiceOnly = (bool) ($sellCashIn['invoice_only'] ?? false);
    $canCreate = (bool) ($sellCashIn['can_create'] ?? false);
    $banks = $sellCashIn['banks'] ?? collect();
    $cashPartyLookupUrl = route('transactions.lookup', [
        'type' => 'cash-in',
        'role' => 'sender',
        'addrbook_type' => Addrbook::cashPartyTypes(),
    ]);
    $initialSender = null;
    if (old('sender_id')) {
        $oldSender = Addrbook::query()->find((int) old('sender_id'));
        if ($oldSender) {
            $initialSender = ['id' => $oldSender->id, 'name' => $oldSender->name];
        }
    }
    $defaultAccount = $sellCashIn['default_account'] ?? null;
    $linked = $sellCashIn['linked'] ?? collect();
    $defaultAmount = (float) ($sellCashIn['default_amount'] ?? 0);
    $defaultDate = $sellCashIn['default_date'] ?? now()->toDateString();
    $minDate = $sellCashIn['min_date'] ?? '';
    $paidTotal = (float) ($sellCashIn['paid_total'] ?? 0);
    $remaining = (float) ($sellCashIn['remaining'] ?? 0);
    $sellTotal = (float) ($sellCashIn['sell_total'] ?? 0);
    $autoEnable = (bool) ($sellCashIn['auto_enable'] ?? false);
    $hideDate = (bool) ($sellCashIn['hide_date'] ?? false);
    $returnInvoiceId = $sellCashIn['return_invoice_id'] ?? null;
    $formAction = $invoiceOnly
        ? ($sellCashIn['cash_in_store_url'] ?? '#')
        : ($transaction ? route('transactions.sell-cash-in.store', $transaction) : '#');
    $hasCashInErrors = $errors->has('amount') || $errors->has('account_id') || $errors->has('date') || $errors->has('sender_id');
    $fmt = fn ($n) => format_amount($n);
    $initialEnabled = ($autoEnable || $hasCashInErrors) ? 'true' : 'false';
    $initialAmount = $hasCashInErrors && old('amount') !== null ? (float) old('amount') : $defaultAmount;
    $showCard = $sellCashIn && ($transaction || $invoiceOnly) && ($canCreate || $linked->isNotEmpty());
@endphp
@if($showCard)
<div class="print:hidden rounded-xl border border-gray-200 bg-white shadow-sm"
     data-testid="sell-cash-in-card"
     x-data="{
        enabled: {{ $initialEnabled }},
        amount: {{ $initialAmount }},
        accountId: @js((string) old('account_id', $defaultAccount['id'] ?? '')),
        senderId: @js((string) old('sender_id', '')),
        date: @js(old('date', $defaultDate)),
        minDate: @js($minDate),
        dateValid() {
            @if($hideDate)
            return true;
            @else
            if (!this.date) return false;
            if (this.minDate && this.date < this.minDate) return false;
            return true;
            @endif
        },
        amountValid() {
            return Number(this.amount) >= 0.01;
        },
        accountValid() {
            return !!this.accountId;
        },
        senderValid() {
            @if($invoiceOnly)
            return !!this.senderId;
            @else
            return true;
            @endif
        },
        canSubmit() {
            return this.enabled && this.dateValid() && this.amountValid() && this.accountValid() && this.senderValid();
        },
     }">
    <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
        <div>
            <h2 class="text-sm font-semibold text-gray-900">Cash In</h2>
            @if($invoiceOnly)
            <p class="mt-0.5 text-xs text-gray-500">Record payment against invoice {{ $sellCashIn['invoice_number'] ?? '' }}. Link a sell later to reconcile stock.</p>
            @else
            <p class="mt-0.5 text-xs text-gray-500">Record payment from {{ $transaction->receiver?->name ?: 'the receiver' }} with the same invoice.</p>
            @endif
            @if($paidTotal > 0.009 || ($autoEnable && ($remaining > 0.009 || $sellTotal > 0.009)))
            <p class="mt-1 text-xs text-gray-600" data-testid="sell-cash-in-summary">
                Paid {{ $fmt($paidTotal) }} of {{ $fmt($sellTotal) }}
                @if($remaining > 0.009)
                    · Remaining {{ $fmt($remaining) }}
                @endif
            </p>
            @endif
        </div>
        @if($canCreate && ! $autoEnable)
        <label class="inline-flex cursor-pointer items-center gap-2" title="Create cash in">
            <span class="text-xs font-medium text-gray-600" x-text="enabled ? 'On' : 'Off'">Off</span>
            <span class="relative inline-flex h-6 w-11 shrink-0 items-center">
                <input type="checkbox" x-model="enabled" data-testid="sell-cash-in-switch"
                       class="peer sr-only">
                <span class="absolute inset-0 rounded-full bg-gray-300 peer-checked:bg-blue-600"></span>
                <span class="absolute left-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
            </span>
        </label>
        @endif
    </div>

    @if($linked->isNotEmpty())
    <div class="border-b border-gray-100 px-4 py-3 sm:px-5">
        <h3 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Linked cash-in ({{ $linked->count() }})</h3>
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
    <form method="POST" action="{{ $formAction }}"
          @if(! $autoEnable) x-show="enabled" x-cloak @endif
          class="space-y-4 px-4 py-4 sm:px-5">
        @csrf
        @if($returnInvoiceId && ! $invoiceOnly)
            <input type="hidden" name="return_invoice_id" value="{{ $returnInvoiceId }}">
        @endif
        @if($hideDate)
            <input type="hidden" name="date" value="{{ $defaultDate }}">
        @endif
        <div class="space-y-4">
            @if($invoiceOnly)
            <div>
                <label for="sell-cash-in-sender-query" class="mb-1 block text-sm font-medium text-gray-700">From (customer)</label>
                <input type="hidden" name="sender_id" x-model="senderId">
                <div x-data="asyncCombobox({
                    endpoint: @js($cashPartyLookupUrl),
                    placeholder: 'Search customer, reseller, supplier, or ledger…',
                    initial: @js($initialSender),
                    onSelect: (item) => { senderId = item ? String(item.id) : ''; }
                })" class="relative">
                    <div class="relative flex h-10 w-full overflow-hidden rounded-lg border focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500"
                         :class="!senderValid() ? 'border-red-400 bg-red-50' : 'border-gray-300'">
                        <input type="text" id="sell-cash-in-sender-query"
                               x-model="query"
                               @input="handleInput()"
                               @focus="handleFocus()"
                               @keydown="handleKeydown($event)"
                               @keyup="handleKeyup($event)"
                               :readonly="keyboardNavLock()"
                               data-testid="sell-cash-in-sender"
                               :placeholder="placeholder"
                               class="flex-1 border-none bg-transparent px-3 py-2 text-sm outline-none placeholder-gray-400"
                               autocomplete="off">
                        <button type="button" @click="open = !open; if(!items.length) doSearch(query)"
                                class="flex shrink-0 items-center px-2 text-gray-400">
                            <svg x-show="!loading" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                            <svg x-show="loading" class="h-4 w-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        </button>
                    </div>
                    <div x-show="open" x-cloak @click.away="open = false" class="combobox-options" x-ref="optionsList">
                        <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400" x-text="emptyMessage()"></div>
                        <template x-for="(item, idx) in items" :key="item.id">
                            <div @click="selectItem(item)"
                                 @mouseenter="activeIndex = idx"
                                 class="combobox-option"
                                 :class="{ 'active': activeIndex === idx }">
                                <span x-text="item.name"></span>
                            </div>
                        </template>
                    </div>
                </div>
                @error('sender_id')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
            @endif
            <div @class([
                'grid grid-cols-1 gap-4',
                'sm:grid-cols-2' => $hideDate || $invoiceOnly,
                'sm:grid-cols-3' => ! $hideDate && ! $invoiceOnly,
            ])>
                @if(! $hideDate)
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
                @endif
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
        </div>
        <div class="flex items-center justify-end gap-3">
            <p x-show="!canSubmit()" x-cloak class="mr-auto text-xs text-gray-400">
                @if($invoiceOnly)
                    Choose customer, bank, and amount to create cash in.
                @elseif($hideDate)
                    Choose a bank and amount to create cash in.
                @else
                    Choose a date, bank, and amount to create cash in.
                @endif
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
