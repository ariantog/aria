@extends('layouts.app')

@section('title', $type === 'in' ? 'New Cash In' : 'New Cash Out')

@section('content')
@php
use App\Models\Addrbook;
$isCashIn = $type === 'in';
$cashPartyTypes = Addrbook::cashPartyTypes();
$cashLookupType = $isCashIn ? 'cash-in' : 'cash-out';
$cashLookupRole = $isCashIn ? 'sender' : 'receiver';
$config = [
    'title'           => $isCashIn ? 'New Cash In' : 'New Cash Out',
    'description'     => $isCashIn ? 'Record cash received from customers, suppliers, resellers, or ledgers.' : 'Record cash payments to customers, suppliers, resellers, or ledgers.',
    'saveLabel'       => $isCashIn ? 'Save Cash In' : 'Save Cash Out',
    'endpoint'        => $isCashIn ? route('transactions.cash-in.store') : route('transactions.cash-out.store'),
    'lookupEndpoint'  => route('transactions.lookup', [
        'type' => $cashLookupType,
        'role' => $cashLookupRole,
        'addrbook_type' => $cashPartyTypes,
    ]),
    'sourceLabel'     => $isCashIn ? 'Name / Source' : 'Name / Recipient',
    'sourcePlaceholder' => $isCashIn ? 'Select source…' : 'Select recipient…',
];
@endphp

<div class="flex flex-col gap-4 p-4"
     x-data="cashForm()"
     x-init="init()">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('transactions.index') }}" class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50">
            <svg class="h-4 w-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $config['title'] }}</h2>
            <p class="mt-0.5 text-sm text-gray-500">{{ $config['description'] }}</p>
        </div>
    </div>

    {{-- Validation errors (kept client-side so entries persist on failure) --}}
    <div x-show="serverErrors.length" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
        <p class="font-medium">Please fix the following:</p>
        <ul class="mt-1 list-disc pl-5">
            <template x-for="(e, i) in serverErrors" :key="i"><li x-text="e"></li></template>
        </ul>
    </div>

    <form method="POST" action="{{ $config['endpoint'] }}" @submit.prevent="handleSubmit()">
        @csrf

        <div class="space-y-5">

            {{-- Account & Date --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $isCashIn ? 'Cash In' : 'Cash Out' }} Details
                    </h3>
                </div>
                <div class="grid grid-cols-1 gap-5 p-5 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="date" x-model="form.date"
                               min="{{ $min_date ?? '' }}"
                               class="w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                               :class="!dateValid() ? 'border-red-400 bg-red-50' : 'border-gray-300'">
                        <p x-show="!dateValid()" x-cloak class="mt-1 text-xs text-red-500">Tanggal harus di bulan ini atau bulan lalu.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bank Account <span class="text-red-500">*</span></label>
                        <select name="account_id" x-model="form.account_id"
                                @change="onAccountChange()"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="">Select bank account…</option>
                            @foreach($bankList as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                        @error('account_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Cash items --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-gray-900">Cash Entries</h3>
                    <button type="button" @click="addRow()"
                            :disabled="!canAddRow()"
                            class="flex items-center gap-1.5 rounded-lg bg-blue-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Row
                    </button>
                </div>

                <div class="hidden grid-cols-12 gap-2 border-b bg-gray-50 px-5 py-2.5 text-[10px] font-medium uppercase tracking-wide text-gray-500 sm:grid">
                    <div class="col-span-4">{{ $config['sourceLabel'] }}</div>
                    <div class="col-span-3">Invoice #</div>
                    <div class="col-span-2">Note</div>
                    <div class="col-span-2 text-right">Total (Rp)</div>
                    <div class="col-span-1 text-center">×</div>
                </div>

                @php
                    $rowInput = 'w-full h-8 min-h-8 box-border rounded border border-gray-200 px-2 py-0 text-sm leading-8 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 [-moz-appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none';
                @endphp
                <div class="divide-y divide-gray-100 px-0">
                    <template x-for="(row, idx) in form.items" :key="row.id">
                        {{-- Ledger hint is its own grid cell so the input row stays even. --}}
                        <div class="flex flex-col gap-3 px-5 py-4 text-sm hover:bg-gray-50 sm:grid sm:grid-cols-12 sm:items-start sm:gap-x-2 sm:gap-y-1 sm:py-2"
                             data-testid="cash-entry-row"
                             :class="rowInvalid(row) ? 'bg-red-50' : ''">
                            {{-- Source / recipient autocomplete --}}
                            <div class="relative order-1 sm:col-span-4"
                                 x-data="asyncCombobox({
                                     endpoint: @js($config['lookupEndpoint']),
                                     placeholder: '{{ $config['sourcePlaceholder'] }}',
                                     onSelect: (item) => onRowSourceSelect(idx, row, item)
                                 })">
                                <label class="mb-1 block text-xs font-medium text-gray-500 sm:hidden">{{ $config['sourceLabel'] }}</label>
                                <div class="relative flex h-8 min-h-8 overflow-hidden rounded border focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500"
                                     :class="rowInvalid(row) && !row.customer_id ? 'border-red-400 bg-red-50' : 'border-gray-200'">
                                    <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()"
                                           @keydown="handleKeydown($event)" @keyup="handleKeyup($event)"
                                           :readonly="keyboardNavLock()"
                                           :id="'source_' + idx"
                                           :placeholder="placeholder" class="flex-1 border-none bg-transparent px-2 text-sm leading-8 outline-none" autocomplete="off">
                                    <span x-show="loading" class="flex items-center pr-1.5"><svg class="h-3.5 w-3.5 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></span>
                                </div>
                                <div x-show="open" class="combobox-options" x-ref="optionsList" style="z-index:60">
                                    <div x-show="!loading && items.length===0" class="px-3 py-2 text-sm text-gray-400" x-text="emptyMessage()"></div>
                                    <template x-for="(item, i) in items" :key="item.id">
                                        <div @mousedown.prevent="selectItem(item)" @mouseenter="activeIndex=i" class="combobox-option" :class="{'active': activeIndex===i}">
                                            <span class="block font-medium" x-text="item.name"></span>
                                            <span x-show="item.ledger_hint" class="block text-xs text-gray-500 line-clamp-2" x-text="item.ledger_hint"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <p x-show="row.customer && row.customer.ledger_hint"
                               x-cloak
                               data-testid="cash-entry-ledger-hint"
                               class="order-2 text-xs leading-4 text-gray-500 sm:order-6 sm:col-span-4 sm:col-start-1"
                               x-text="row.customer.ledger_hint"></p>
                            <div class="order-3 sm:order-2 sm:col-span-3">
                                <label class="mb-1 block text-xs font-medium text-gray-500 sm:hidden">Invoice #</label>
                                <input type="text" x-model="row.invoice" placeholder="Invoice #"
                                       @keydown="fieldKeydown(idx, 'invoice', $event)"
                                       @keyup="fieldKeyup(idx, 'invoice', $event)"
                                       :id="'invoice_' + idx"
                                       class="{{ $rowInput }}">
                            </div>
                            <div class="order-4 sm:order-3 sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-500 sm:hidden">Note</label>
                                <input type="text" x-model="row.note" placeholder="Note"
                                       @keydown="fieldKeydown(idx, 'note', $event)"
                                       @keyup="fieldKeyup(idx, 'note', $event)"
                                       :id="'note_' + idx"
                                       class="{{ $rowInput }}">
                            </div>
                            <div class="order-5 sm:order-4 sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-500 sm:hidden">Total (Rp)</label>
                                <input type="number" x-model.number="row.total" placeholder="0" min="0" step="any"
                                       @input="onTotalChange(row)"
                                       @keydown="fieldKeydown(idx, 'total', $event)"
                                       @keyup="fieldKeyup(idx, 'total', $event)"
                                       :id="'total_' + idx"
                                       class="{{ $rowInput }} text-right"
                                       :class="rowInvalid(row) && !(Number(row.total) >= 0.01) ? 'border-red-400 bg-red-50' : ''">
                            </div>
                            <div class="order-6 sm:order-5 sm:col-span-1 sm:text-center">
                                <button type="button" @click="removeRow(idx)" :disabled="form.items.length === 1"
                                        class="inline-flex h-8 w-full items-center justify-center gap-1.5 rounded border border-red-200 text-sm text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-30 sm:w-8 sm:border-0 sm:text-gray-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <span class="sm:hidden">Remove</span>
                                </button>
                            </div>
                            <div x-show="isPkpBank()" x-cloak class="order-7 sm:col-span-12 rounded-lg border border-dashed border-gray-200 bg-gray-50/80 px-3 py-2.5">
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                                    <input type="checkbox"
                                           x-model="row.record_ppn"
                                           @change="onRecordPpnToggle(row)"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    Record PPN {{ $isCashIn ? 'keluaran' : 'masukan' }}
                                </label>
                                <div x-show="row.record_ppn" x-cloak class="mt-2 space-y-2">
                                    <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                                        <input type="checkbox"
                                               x-model="row.record_pph"
                                               @change="onRecordPphToggle(row)"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        Include PPh withholding ({{ (float) ($pph_rate ?? 10) }}% of DPP)
                                    </label>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-1 block text-[10px] font-medium uppercase tracking-wide text-gray-500">DPP (Rp)</label>
                                        <input type="number"
                                               x-model.number="row.ppn_dpp"
                                               min="0"
                                               step="any"
                                               placeholder="0"
                                               @input="markPpnManual(row)"
                                               class="{{ $rowInput }}"
                                               :class="rowInvalid(row) && row.record_ppn && !(Number(row.ppn_dpp) >= 0.01) ? 'border-red-400 bg-red-50' : ''">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[10px] font-medium uppercase tracking-wide text-gray-500">PPN (Rp)</label>
                                        <input type="number"
                                               x-model.number="row.ppn"
                                               min="0"
                                               step="any"
                                               placeholder="0"
                                               @input="markPpnManual(row)"
                                               class="{{ $rowInput }}"
                                               :class="rowInvalid(row) && row.record_ppn && !(Number(row.ppn) >= 0.01) ? 'border-red-400 bg-red-50' : ''">
                                    </div>
                                    <div x-show="row.record_pph" x-cloak>
                                        <label class="mb-1 block text-[10px] font-medium uppercase tracking-wide text-gray-500">PPh (Rp)</label>
                                        <input type="number"
                                               x-model.number="row.pph"
                                               min="0"
                                               step="any"
                                               placeholder="0"
                                               @input="markPpnManual(row)"
                                               class="{{ $rowInput }}"
                                               :class="rowInvalid(row) && row.record_pph && !(Number(row.pph) >= 0.01) ? 'border-red-400 bg-red-50' : ''">
                                    </div>
                                    </div>
                                </div>
                                <p x-show="row.record_ppn" x-cloak class="mt-1 text-[11px] text-gray-500">
                                    DPP, PPN<span x-show="row.record_pph">, and PPh</span> are calculated from Total. Edit any field if the invoice differs slightly.
                                </p>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Grand total --}}
                <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-5 py-3">
                    <span class="text-sm font-semibold text-gray-900">Grand Total</span>
                    <span class="text-lg font-bold tabular-nums text-blue-700"
                          x-text="'Rp ' + formatAmountId(grandTotal())"></span>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                <span x-show="!canSubmit()" x-cloak class="text-center text-xs text-gray-400 sm:mr-auto sm:text-left">
                    Add a valid date, bank account, and at least one complete entry to enable Save.
                </span>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:gap-3">
                    <button type="button" onclick="window.history.back()"
                            class="rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Discard
                    </button>
                    <button type="submit" :disabled="submitting || !canSubmit()"
                            class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500">
                        <span x-show="!submitting">{{ $config['saveLabel'] }}</span>
                        <span x-show="submitting">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
const _CashMinDate = '{{ $min_date ?? '' }}';
const _CashEndpoint = @js($config['endpoint']);
const _CashCsrf = '{{ csrf_token() }}';
const _TxIndex = '{{ route('transactions.index') }}';
const _CashMaxRows = 7;
const _CashDefaultAccount = @json($defaultAccount ?? null);
const _PkpBankIds = @json($pkpBankIds ?? []);
const _PpnRate = {{ (float) ($ppn_rate ?? 11) }};
const _PphRate = {{ (float) ($pph_rate ?? 10) }};

function cashForm() {
    const today = new Date().toISOString().split('T')[0];
    const startDate = (_CashMinDate && today < _CashMinDate) ? _CashMinDate : today;
    return {
        ...submitGuardFields(),
        touched: false,
        serverErrors: [],
        _fieldKeyHandled: false,
        form: {
            date: startDate,
            account_id: '',
            account: null,
            items: [newRow()],
        },

        init() {
            if (_CashDefaultAccount) {
                this.form.account_id = String(_CashDefaultAccount.id);
                this.form.account = _CashDefaultAccount;
            }
        },

        isPkpBank() {
            return _PkpBankIds.includes(Number(this.form.account_id));
        },

        onAccountChange() {
            if (this.isPkpBank()) {
                return;
            }
            this.form.items.forEach((row) => this.resetRowPpn(row));
        },

        resetRowPpn(row) {
            row.record_ppn = false;
            row.record_pph = false;
            row.ppn_dpp = null;
            row.ppn = null;
            row.pph = null;
            row.ppn_manual = false;
        },

        markPpnManual(row) {
            row.ppn_manual = true;
        },

        syncTaxFromTotal(row) {
            if (! row.record_ppn) {
                return;
            }
            const payment = Number(row.total || 0);
            if (payment < 0.01) {
                row.ppn_dpp = null;
                row.ppn = null;
                row.pph = null;
                return;
            }
            const ppnRate = _PpnRate / 100;
            const pphRate = row.record_pph ? (_PphRate / 100) : 0;
            const divisor = 1 + ppnRate - pphRate;
            row.ppn_dpp = Math.round(payment / divisor * 100) / 100;
            row.ppn = Math.round(row.ppn_dpp * ppnRate * 100) / 100;
            row.pph = row.record_pph ? Math.round(row.ppn_dpp * pphRate * 100) / 100 : null;
        },

        onTotalChange(row) {
            if (row.record_ppn && ! row.ppn_manual) {
                this.syncTaxFromTotal(row);
            }
        },

        onRecordPpnToggle(row) {
            if (! row.record_ppn) {
                this.resetRowPpn(row);
                row.record_ppn = false;
                return;
            }
            row.ppn_manual = false;
            this.syncTaxFromTotal(row);
        },

        onRecordPphToggle(row) {
            if (! row.record_pph) {
                row.pph = null;
            }
            if (! row.ppn_manual) {
                this.syncTaxFromTotal(row);
            }
        },

        addRow() {
            if (!this.canAddRow()) return;
            this.form.items.push(newRow());
        },

        canAddRow() {
            return this.form.items.length < _CashMaxRows;
        },

        onRowSourceSelect(idx, row, item) {
            row.customer_id = item ? String(item.id) : '';
            row.customer = item;
            if (!item) return;
            suppressFieldNavigation(400);
            deferFocusElement('invoice_' + idx);
        },

        removeRow(idx) {
            if (this.form.items.length === 1) { this.form.items[0] = newRow(); return; }
            this.form.items.splice(idx, 1);
        },

        grandTotal() {
            return this.form.items.reduce((s, i) => s + Number(i.total || 0), 0);
        },

        // ---- validation (used to enable/disable submit and highlight fields) ----
        dateValid() {
            if (!this.form.date) return false;
            if (_CashMinDate && this.form.date < _CashMinDate) return false;
            return true;
        },
        accountValid() { return !!this.form.account_id; },
        rowEmpty(row) {
            return !row.customer_id && (row.total === null || row.total === '' || Number(row.total) === 0)
                && !row.invoice && !row.note && !row.record_ppn;
        },
        rowValid(row) {
            if (!row.customer_id || !(Number(row.total) >= 0.01)) {
                return false;
            }
            if (row.record_ppn) {
                if (!(Number(row.ppn_dpp) >= 0.01) || !(Number(row.ppn) >= 0.01)) {
                    return false;
                }
                if (row.record_pph && !(Number(row.pph) >= 0.01)) {
                    return false;
                }
            }
            return true;
        },
        rowInvalid(row) {
            return !this.rowEmpty(row) && !this.rowValid(row);
        },
        filledRows() { return this.form.items.filter(r => !this.rowEmpty(r)); },
        canSubmit() {
            const rows = this.filledRows();
            return this.dateValid() && this.accountValid() && rows.length >= 1 && rows.every(r => this.rowValid(r));
        },

        focusNext(idx, field) {
            if (field === 'next') {
                // Move to next row or add row (capped at _CashMaxRows)
                if (idx < this.form.items.length - 1) {
                    deferFocusElement('source_' + (idx + 1), false);
                } else if (this.canAddRow()) {
                    this.addRow();
                    deferFocusElement('source_' + (idx + 1), false);
                }
                return;
            }
            deferFocusElement(field + '_' + idx);
        },

        // Bare keydown/keyup (not Alpine .enter) so Android/IME keyboards work.
        fieldKeydown(idx, field, e) {
            if (isFieldNavigationSuppressed()) {
                e.preventDefault();
                return;
            }
            this._fieldKeyHandled = false;
            if (this._processFieldKey(idx, field, e)) {
                this._fieldKeyHandled = true;
                e.preventDefault();
            }
        },

        fieldKeyup(idx, field, e) {
            if (isFieldNavigationSuppressed()) {
                e.preventDefault();
                return;
            }
            if (this._fieldKeyHandled) {
                this._fieldKeyHandled = false;
                return;
            }
            if (this._processFieldKey(idx, field, e)) {
                this._fieldKeyHandled = true;
                e.preventDefault();
            }
        },

        _processFieldKey(idx, field, e) {
            if (normalizeNavigationKey(e) !== 'Enter') return false;
            suppressFieldNavigation(400);
            if (field === 'invoice') { this.focusNext(idx, 'note'); return true; }
            if (field === 'note') { this.focusNext(idx, 'total'); return true; }
            if (field === 'total') { this.focusNext(idx, 'next'); return true; }
            return false;
        },

        async handleSubmit() {
            this.touched = true;
            this.serverErrors = [];
            if (!this.canSubmit()) return;
            if (!beginSubmit(this)) return;

            this.filledRows().forEach((row) => {
                if (row.record_ppn && ! row.ppn_manual) {
                    this.syncTaxFromTotal(row);
                }
            });

            const form = this.$el.querySelector('form');
            if (form) window.markFormSubmitInFlight(form);
            let keepLocked = false;

            const payload = {
                date: this.form.date,
                account_id: this.form.account_id,
                items: this.filledRows().map(r => ({
                    customer_id: r.customer_id,
                    invoice: r.invoice,
                    note: r.note,
                    total: Number(r.total || 0),
                    record_ppn: !!r.record_ppn,
                    record_pph: !!r.record_pph,
                    ppn_dpp: r.record_ppn ? Number(r.ppn_dpp || 0) : null,
                    ppn: r.record_ppn ? Number(r.ppn || 0) : null,
                    pph: r.record_ppn && r.record_pph ? Number(r.pph || 0) : null,
                })),
            };

            try {
                const res = await fetch(_CashEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': _CashCsrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        ...idempotencyHeaders(this),
                    },
                    // Don't follow the server redirect; keep its flash and send the user to the list.
                    redirect: 'manual',
                    body: JSON.stringify(payload),
                });

                if (res.type === 'opaqueredirect' || res.status === 0 || (res.status >= 200 && res.status < 400)) {
                    keepLocked = true;
                    window.location.href = _TxIndex;
                    return;
                }

                if (res.status === 422) {
                    const data = await res.json().catch(() => ({}));
                    this.serverErrors = data.errors ? Object.values(data.errors).flat() : [data.message || 'Please fix the highlighted fields.'];
                } else {
                    this.serverErrors = ['Something went wrong (HTTP ' + res.status + '). Your entries are still here — please try again.'];
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (e) {
                this.serverErrors = ['Network error — your entries are still here. Please try again.'];
            } finally {
                if (!keepLocked) {
                    endSubmit(this);
                    if (form) window.releaseFormSubmitGuard(form);
                }
            }
        }
    };
}

function newRow() {
    return {
        id: Math.random().toString(36).substr(2, 9),
        customer_id: '',
        customer: null,
        invoice: '',
        note: '',
        total: null,
        record_ppn: false,
        record_pph: false,
        ppn_dpp: null,
        ppn: null,
        pph: null,
        ppn_manual: false,
    };
}
</script>
@endpush
@endsection
