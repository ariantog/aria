@extends('layouts.app')

@section('title', $type === 'in' ? 'New Cash In' : 'New Cash Out')

@section('content')
@php
$isCashIn = $type === 'in';
$config = [
    'title'           => $isCashIn ? 'New Cash In' : 'New Cash Out',
    'description'     => $isCashIn ? 'Record cash received from customers or sources.' : 'Record cash payments to suppliers or recipients.',
    'saveLabel'       => $isCashIn ? 'Save Cash In' : 'Save Cash Out',
    'endpoint'        => $isCashIn ? route('transactions.cash-in.store') : route('transactions.cash-out.store'),
    'lookupType'      => $isCashIn ? 'buy' : 'cash-out',
    'lookupRole'      => $isCashIn ? 'sender' : 'receiver',
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
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm max-w-3xl">
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
                        <p x-show="!dateValid()" x-cloak class="mt-1 text-xs text-red-500">A valid date on/after the book-closing date is required.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bank Account <span class="text-red-500">*</span></label>
                        @if($isCashIn)
                        {{-- Cash-in: bank is the receiver, use combobox --}}
                        <div x-data="asyncCombobox({
                            endpoint: '{{ route('transactions.lookup', ['type' => $config['lookupType'], 'role' => 'receiver']) }}',
                            additionalParams: { addrbook_type: 3 },
                            placeholder: 'Select bank account…',
                            onSelect: (item) => { form.account_id = item ? String(item.id) : '' }
                        })" class="relative">
                            <input type="hidden" name="account_id" :value="form.account_id">
                            <div class="relative flex h-10 overflow-hidden rounded-lg border border-gray-300 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
                                <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()"
                                       @keydown="handleKeydown($event)" @keyup="handleKeyup($event)"
                                       :readonly="keyboardNavLock()"
                                       :placeholder="placeholder" class="flex-1 border-none bg-transparent px-3 text-sm outline-none" autocomplete="off">
                                <span x-show="loading" class="flex items-center pr-2"><svg class="h-4 w-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></span>
                            </div>
                            <div x-show="open" @click.away="open=false" class="combobox-options" x-ref="optionsList">
                                <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400">No banks found.</div>
                                <template x-for="(item, idx) in items" :key="item.id">
                                    <div @click="selectItem(item)" @mouseenter="activeIndex=idx" class="combobox-option" :class="{'active': activeIndex===idx}">
                                        <span x-text="item.name"></span>
                                        <span class="ml-auto text-xs opacity-60" x-text="item.balance !== undefined ? 'Rp '+Number(item.balance).toLocaleString('id-ID') : ''"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        @else
                        {{-- Cash-out: use simple select from bankList --}}
                        <select name="account_id" x-model="form.account_id"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="">Select bank account…</option>
                            @foreach($bankList as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                        @endif
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
                        <div class="grid grid-cols-12 items-center gap-2 px-5 py-2 text-sm hover:bg-gray-50"
                             :class="rowInvalid(row) ? 'bg-red-50' : ''">
                            {{-- Source / recipient autocomplete --}}
                            <div class="relative col-span-4"
                                 x-data="asyncCombobox({
                                     endpoint: '{{ route('transactions.lookup', ['type' => $config['lookupType'], 'role' => $config['lookupRole']]) }}',
                                     placeholder: '{{ $config['sourcePlaceholder'] }}',
                                     onSelect: (item) => onRowSourceSelect(idx, row, item)
                                 })">
                                <div class="relative flex h-8 min-h-8 overflow-hidden rounded border focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500"
                                     :class="rowInvalid(row) && !row.customer_id ? 'border-red-400 bg-red-50' : 'border-gray-200'">
                                    <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()"
                                           @keydown="handleKeydown($event)" @keyup="handleKeyup($event)"
                                           :readonly="keyboardNavLock()"
                                           :id="'source_' + idx"
                                           :placeholder="placeholder" class="flex-1 border-none bg-transparent px-2 text-sm leading-8 outline-none" autocomplete="off">
                                    <span x-show="loading" class="flex items-center pr-1.5"><svg class="h-3.5 w-3.5 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></span>
                                </div>
                                <div x-show="open" @click.away="open=false" class="combobox-options" x-ref="optionsList" style="z-index:60">
                                    <div x-show="!loading && items.length===0" class="px-3 py-2 text-sm text-gray-400">Nothing found.</div>
                                    <template x-for="(item, i) in items" :key="item.id">
                                        <div @mousedown.prevent="selectItem(item)" @mouseenter="activeIndex=i" class="combobox-option" :class="{'active': activeIndex===i}">
                                            <span x-text="item.name"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div class="col-span-3">
                                <input type="text" x-model="row.invoice" placeholder="Invoice #"
                                       @keydown="fieldKeydown(idx, 'invoice', $event)"
                                       @keyup="fieldKeyup(idx, 'invoice', $event)"
                                       :id="'invoice_' + idx"
                                       class="{{ $rowInput }}">
                            </div>
                            <div class="col-span-2">
                                <input type="text" x-model="row.note" placeholder="Note"
                                       @keydown="fieldKeydown(idx, 'note', $event)"
                                       @keyup="fieldKeyup(idx, 'note', $event)"
                                       :id="'note_' + idx"
                                       class="{{ $rowInput }}">
                            </div>
                            <div class="col-span-2">
                                <input type="number" x-model.number="row.total" placeholder="0" min="0" step="any"
                                       @keydown="fieldKeydown(idx, 'total', $event)"
                                       @keyup="fieldKeyup(idx, 'total', $event)"
                                       :id="'total_' + idx"
                                       class="{{ $rowInput }} text-right"
                                       :class="rowInvalid(row) && !(Number(row.total) >= 0.01) ? 'border-red-400 bg-red-50' : ''">
                            </div>
                            <div class="col-span-1 text-center">
                                <button type="button" @click="removeRow(idx)" :disabled="form.items.length === 1"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded text-gray-400 hover:bg-red-50 hover:text-red-500 disabled:opacity-30">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Grand total --}}
                <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-5 py-3">
                    <span class="text-sm font-semibold text-gray-900">Grand Total</span>
                    <span class="text-lg font-bold tabular-nums text-blue-700"
                          x-text="'Rp ' + grandTotal().toLocaleString('id-ID')"></span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <span x-show="!canSubmit()" x-cloak class="text-xs text-gray-400">
                    Add a valid date, bank account, and at least one complete entry to enable Save.
                </span>
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
    </form>
</div>

@push('scripts')
<script>
const _CashMinDate = '{{ $min_date ?? '' }}';
const _CashEndpoint = @js($config['endpoint']);
const _CashCsrf = '{{ csrf_token() }}';
const _TxIndex = '{{ route('transactions.index') }}';
const _CashMaxRows = 7;

function cashForm() {
    const today = new Date().toISOString().split('T')[0];
    const startDate = (_CashMinDate && today < _CashMinDate) ? _CashMinDate : today;
    return {
        submitting: false,
        touched: false,
        serverErrors: [],
        _fieldKeyHandled: false,
        form: {
            date: startDate,
            account_id: '',
            account: null,
            items: [newRow()],
        },

        init() {},

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
            // Defer focus until after keyup: if we move focus synchronously on
            // keydown, the same Enter's keyup lands on invoice and advances to note.
            setTimeout(() => {
                const el = document.getElementById('invoice_' + idx);
                if (el) { el.focus(); el.select?.(); }
            }, 0);
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
                && !row.invoice && !row.note;
        },
        rowValid(row) {
            return !!row.customer_id && Number(row.total) >= 0.01;
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
            let id;
            if (field === 'next') {
                // Move to next row or add row (capped at _CashMaxRows)
                if (idx < this.form.items.length - 1) {
                    id = 'source_' + (idx + 1);
                } else if (this.canAddRow()) {
                    this.addRow();
                    this.$nextTick(() => {
                        const el = document.getElementById('source_' + (idx + 1));
                        if (el) el.focus();
                    });
                    return;
                } else {
                    return;
                }
            } else {
                id = field + '_' + idx;
            }
            this.$nextTick(() => {
                const el = document.getElementById(id);
                if (el) { el.focus(); if (el.select) el.select(); }
            });
        },

        // Bare keydown/keyup (not Alpine .enter) so Android/IME keyboards work.
        fieldKeydown(idx, field, e) {
            this._fieldKeyHandled = false;
            if (this._processFieldKey(idx, field, e)) {
                this._fieldKeyHandled = true;
                e.preventDefault();
            }
        },

        fieldKeyup(idx, field, e) {
            if (this._fieldKeyHandled) {
                this._fieldKeyHandled = false;
                return;
            }
            if (this._processFieldKey(idx, field, e)) {
                e.preventDefault();
            }
        },

        _processFieldKey(idx, field, e) {
            if (normalizeNavigationKey(e) !== 'Enter') return false;
            if (field === 'invoice') { this.focusNext(idx, 'note'); return true; }
            if (field === 'note') { this.focusNext(idx, 'total'); return true; }
            if (field === 'total') { this.focusNext(idx, 'next'); return true; }
            return false;
        },

        async handleSubmit() {
            this.touched = true;
            this.serverErrors = [];
            if (!this.canSubmit() || this.submitting) return;

            this.submitting = true;
            const payload = {
                date: this.form.date,
                account_id: this.form.account_id,
                items: this.filledRows().map(r => ({
                    customer_id: r.customer_id,
                    invoice: r.invoice,
                    note: r.note,
                    total: Number(r.total || 0),
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
                    },
                    // Don't follow the server redirect; keep its flash and send the user to the list.
                    redirect: 'manual',
                    body: JSON.stringify(payload),
                });

                if (res.type === 'opaqueredirect' || res.status === 0 || (res.status >= 200 && res.status < 400)) {
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
                this.submitting = false;
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
    };
}
</script>
@endpush
@endsection
