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

    @if($errors->any())
    <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $config['endpoint'] }}" @submit.prevent="handleSubmit($event)">
        @csrf

        <div class="space-y-5 max-w-3xl">

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
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        @error('date')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
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
                                <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)"
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
                            class="flex items-center gap-1.5 rounded-lg bg-blue-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-800">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Row
                    </button>
                </div>

                <div class="hidden grid-cols-12 gap-2 border-b bg-gray-50 px-5 py-2.5 text-xs font-medium uppercase text-gray-500 md:grid">
                    <div class="col-span-4">{{ $config['sourceLabel'] }}</div>
                    <div class="col-span-3">Invoice #</div>
                    <div class="col-span-2">Note</div>
                    <div class="col-span-2 text-right">Total (Rp)</div>
                    <div class="col-span-1 text-center">×</div>
                </div>

                <div class="divide-y divide-gray-100">
                    <template x-for="(row, idx) in form.items" :key="row.id">
                        <div class="grid grid-cols-1 items-start gap-3 px-5 py-3 md:grid-cols-12 md:items-center">
                            {{-- Source / recipient autocomplete --}}
                            <div class="md:col-span-4"
                                 x-data="asyncCombobox({
                                     endpoint: '{{ route('transactions.lookup', ['type' => $config['lookupType'], 'role' => $config['lookupRole']]) }}',
                                     placeholder: '{{ $config['sourcePlaceholder'] }}',
                                     onSelect: (item) => { row.customer_id = item ? String(item.id) : ''; row.customer = item; }
                                 })">
                                <div class="relative flex h-9 overflow-hidden rounded-lg border border-gray-300 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
                                    <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)"
                                           :placeholder="placeholder" class="flex-1 border-none bg-transparent px-2 text-sm outline-none" autocomplete="off">
                                    <span x-show="loading" class="flex items-center pr-1.5"><svg class="h-3.5 w-3.5 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></span>
                                </div>
                                <div x-show="open" @click.away="open=false" class="combobox-options" x-ref="optionsList" style="z-index:60">
                                    <div x-show="!loading && items.length===0" class="px-3 py-2 text-sm text-gray-400">Nothing found.</div>
                                    <template x-for="(item, i) in items" :key="item.id">
                                        <div @click="selectItem(item)" @mouseenter="activeIndex=i" class="combobox-option" :class="{'active': activeIndex===i}">
                                            <span x-text="item.name"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div class="md:col-span-3">
                                <input type="text" x-model="row.invoice_number" placeholder="Invoice #"
                                       @keydown.enter.prevent="focusNext(idx, 'note')"
                                       :id="'invoice_' + idx"
                                       class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div class="md:col-span-2">
                                <input type="text" x-model="row.note" placeholder="Note"
                                       @keydown.enter.prevent="focusNext(idx, 'total')"
                                       :id="'note_' + idx"
                                       class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div class="md:col-span-2">
                                <input type="number" x-model.number="row.total" placeholder="0" min="0"
                                       @keydown.enter.prevent="focusNext(idx, 'next')"
                                       :id="'total_' + idx"
                                       class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-right text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div class="md:col-span-1 text-center">
                                <button type="button" @click="removeRow(idx)" :disabled="form.items.length === 1"
                                        class="flex h-7 w-7 items-center justify-center rounded text-gray-400 hover:bg-red-50 hover:text-red-500 mx-auto disabled:opacity-30">
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

            <div class="flex justify-end gap-3">
                <button type="button" onclick="window.history.back()"
                        class="rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Discard
                </button>
                <button type="submit" :disabled="submitting"
                        class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-60">
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

function cashForm() {
    const today = new Date().toISOString().split('T')[0];
    const startDate = (_CashMinDate && today < _CashMinDate) ? _CashMinDate : today;
    return {
        submitting: false,
        form: {
            date: startDate,
            account_id: '',
            account: null,
            items: [newRow()],
        },

        init() {},

        addRow() {
            this.form.items.push(newRow());
        },

        removeRow(idx) {
            if (this.form.items.length === 1) return;
            this.form.items.splice(idx, 1);
        },

        grandTotal() {
            return this.form.items.reduce((s, i) => s + Number(i.total || 0), 0);
        },

        focusNext(idx, field) {
            let id;
            if (field === 'next') {
                // Move to next row or add row
                if (idx < this.form.items.length - 1) {
                    id = 'invoice_' + (idx + 1);
                } else {
                    this.addRow();
                    this.$nextTick(() => {
                        const el = document.getElementById('invoice_' + (idx + 1));
                        if (el) el.focus();
                    });
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

        handleSubmit(event) {
            this.submitting = true;
            // Build the real form data
            const form = event.target;

            // Remove any previously injected hidden fields
            form.querySelectorAll('.dyn-field').forEach(el => el.remove());

            const add = (name, value) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                input.className = 'dyn-field';
                form.appendChild(input);
            };

            add('date', this.form.date);
            add('account_id', this.form.account_id);

            this.form.items.forEach((item, i) => {
                add(`items[${i}][customer_id]`, item.customer_id);
                add(`items[${i}][invoice_number]`, item.invoice_number);
                add(`items[${i}][note]`, item.note);
                add(`items[${i}][total]`, item.total);
            });

            form.submit();
        }
    };
}

function newRow() {
    return {
        id: Math.random().toString(36).substr(2, 9),
        customer_id: '',
        customer: null,
        invoice_number: '',
        note: '',
        total: 0,
    };
}
</script>
@endpush
@endsection
