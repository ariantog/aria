@extends('layouts.app')

@section('title', 'New ' . ucfirst($type) . ' Transaction')

@section('content')
<div class="flex flex-col gap-4 p-4"
     x-data="createTransaction()"
     x-init="init()">

    {{-- Header --}}
    <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div class="flex items-center gap-3">
            <a href="{{ route('transactions.index') }}" class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50">
                <svg class="h-4 w-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-gray-900">New {{ ucfirst($type) }} Transaction</h2>
                <p class="mt-0.5 text-sm text-gray-500">Create a new {{ $type }} transaction and add items.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="window.history.back()"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Discard
            </button>
            <button type="button" @click="submitForm()"
                    :disabled="submitting || !canSubmit()"
                    class="flex min-w-36 items-center justify-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500">
                <svg x-show="submitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span>Save Transaction</span>
            </button>
        </div>
    </div>

    {{-- Validation errors — kept client-side so entries persist on failure --}}
    <div x-show="serverErrors.length" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-3">
        <p class="text-sm font-medium text-red-800">Please fix the following:</p>
        <ul class="mt-1 list-disc pl-5 text-sm text-red-700">
            <template x-for="(e, i) in serverErrors" :key="i"><li x-text="e"></li></template>
        </ul>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Left column --}}
        <div class="space-y-5 xl:col-span-2">

            {{-- Basic Info --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Basic Info
                    </h3>
                </div>
                <div class="grid grid-cols-1 gap-5 p-5 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" x-model="form.date"
                               min="{{ $min_date ?? '' }}"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                               :class="{'border-red-500': errors.date}">
                        <p x-show="errors.date" x-text="errors.date" class="mt-1 text-xs text-red-500"></p>
                    </div>
                    @if($type !== 'move')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                        <input type="date" x-model="form.due_date"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                    @else
                    <div></div>
                    @endif

                    {{-- Sender combobox --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ $config['sender_label'] }} <span class="text-red-500">*</span>
                        </label>
                        <div x-data="asyncCombobox({
                            endpoint: @js($config['sender_route']),
                            placeholder: 'Select {{ $config['sender_label'] }}...',
                            initial: @js(isset($prefill) ? ($prefill['sender'] ?? null) : null),
                            onSelect: (item) => { form.sender_id = item ? String(item.id) : ''; form.sender = item; recalcTotals(); }
                        })" class="relative">
                            <div class="relative flex h-10 w-full overflow-hidden rounded-lg border focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500"
                                 :class="errors.sender_id ? 'border-red-500' : 'border-gray-300'">
                                <input type="text"
                                       x-model="query"
                                       @input="handleInput()"
                                       @focus="handleFocus()"
                                       @keydown="handleKeydown($event)"
                                       @keyup="handleKeyup($event)"
                                       :readonly="keyboardNavLock()"
                                       :placeholder="placeholder"
                                       class="flex-1 border-none bg-transparent px-3 py-2 text-sm outline-none placeholder-gray-400"
                                       autocomplete="off">
                                <button type="button" @click="open = !open; if(!items.length) doSearch(query)"
                                        class="flex items-center px-2 text-gray-400">
                                    <svg x-show="!loading" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                    <svg x-show="loading" class="h-4 w-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </button>
                            </div>
                            <div x-show="open" x-cloak @click.away="open = false" class="combobox-options" x-ref="optionsList">
                                <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400">Nothing found.</div>
                                <template x-for="(item, idx) in items" :key="item.id">
                                    <div @click="selectItem(item)"
                                         @mouseenter="activeIndex = idx"
                                         class="combobox-option"
                                         :class="{ 'active': activeIndex === idx }">
                                        <span x-text="item.name"></span>
                                        <span x-show="item.balance !== undefined" x-text="' — Rp ' + Number(item.balance||0).toLocaleString('id-ID')" class="ml-auto text-xs opacity-60"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <p x-show="errors.sender_id" x-text="errors.sender_id" class="mt-1 text-xs text-red-500"></p>
                    </div>

                    {{-- Receiver combobox --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ $config['receiver_label'] }} <span class="text-red-500">*</span>
                        </label>
                        <div x-data="asyncCombobox({
                            endpoint: @js($config['receiver_route']),
                            placeholder: 'Select {{ $config['receiver_label'] }}...',
                            initial: @js(isset($prefill) ? ($prefill['receiver'] ?? null) : null),
                            onSelect: (item) => { form.receiver_id = item ? String(item.id) : ''; form.receiver = item; recalcTotals(); }
                        })" class="relative">
                            <div class="relative flex h-10 w-full overflow-hidden rounded-lg border focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500"
                                 :class="errors.receiver_id ? 'border-red-500' : 'border-gray-300'">
                                <input type="text"
                                       x-model="query"
                                       @input="handleInput()"
                                       @focus="handleFocus()"
                                       @keydown="handleKeydown($event)"
                                       @keyup="handleKeyup($event)"
                                       :readonly="keyboardNavLock()"
                                       :placeholder="placeholder"
                                       class="flex-1 border-none bg-transparent px-3 py-2 text-sm outline-none placeholder-gray-400"
                                       autocomplete="off">
                                <button type="button" @click="open = !open; if(!items.length) doSearch(query)"
                                        class="flex items-center px-2 text-gray-400">
                                    <svg x-show="!loading" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                    <svg x-show="loading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </button>
                            </div>
                            <div x-show="open" x-cloak @click.away="open = false" class="combobox-options" x-ref="optionsList">
                                <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400">Nothing found.</div>
                                <template x-for="(item, idx) in items" :key="item.id">
                                    <div @click="selectItem(item)"
                                         @mouseenter="activeIndex = idx"
                                         class="combobox-option"
                                         :class="{ 'active': activeIndex === idx }">
                                        <span x-text="item.name"></span>
                                        <span x-show="item.balance !== undefined" x-text="' — Rp ' + Number(item.balance||0).toLocaleString('id-ID')" class="ml-auto text-xs opacity-60"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <p x-show="errors.receiver_id" x-text="errors.receiver_id" class="mt-1 text-xs text-red-500"></p>
                    </div>
                </div>
            </div>

            {{-- Transaction Details --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-gray-900">Transaction Details</h3>
                </div>
                <div class="grid grid-cols-1 gap-5 p-5 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Number</label>
                        <input type="text" x-model="form.invoice_number" placeholder="INV-202X-XXX"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                        <textarea x-model="form.note" rows="2" placeholder="Optional notes..."
                                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"></textarea>
                    </div>
                </div>
            </div>

            {{-- Line Items --}}
            @php $isMove = $type === 'move'; @endphp
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-gray-900">🛒 Line Items</h3>
                    <div class="flex gap-2">
                        <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Batch CSV
                            <input type="file" accept=".csv,.txt" class="hidden" @change="uploadCSV($event)">
                        </label>
                        <button type="button" @click="addItemRow(true)"
                                class="flex items-center gap-1.5 rounded-lg bg-blue-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-800">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Row
                        </button>
                    </div>
                </div>

                {{-- Stock warning --}}
                <div x-show="hasStockWarning()" x-cloak
                     class="mx-5 mt-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                    <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    <span>One or more items exceed available warehouse stock (shown in red).</span>
                </div>

                <div x-show="errors.items" class="mx-5 mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" x-text="errors.items"></div>

                {{-- Table header --}}
                <div class="hidden grid-cols-12 gap-2 border-b bg-gray-50 px-5 py-2.5 text-[10px] font-medium uppercase tracking-wide text-gray-500 sm:grid">
                    <div class="col-span-2">Code / Barcode</div>
                    <div class="col-span-3">Item Name</div>
                    <div class="col-span-1 text-center">Qty</div>
                    <div class="{{ $isMove ? 'col-span-2' : 'col-span-1' }} text-center">Whs. Stock</div>
                    @unless($isMove)<div class="col-span-1 text-right">Disc %</div>@endunless
                    <div class="col-span-2 text-right">Price</div>
                    <div class="col-span-1 text-right">Subtotal</div>
                    <div class="col-span-1 text-center">×</div>
                </div>

                {{-- Item rows --}}
                @php
                    $rowInput = 'w-full h-8 rounded border border-gray-200 px-2 text-sm leading-tight focus:border-blue-500 focus:ring-1 focus:ring-blue-500';
                @endphp
                <div class="divide-y divide-gray-100 px-0">
                    <template x-for="(item, idx) in form.items" :key="item.uid">
                        <div class="grid grid-cols-12 items-center gap-2 px-5 py-2 text-sm hover:bg-gray-50"
                             :class="(isOverStock(item) || itemInvalid(item)) ? 'bg-red-50' : ''">
                            {{-- Code / barcode --}}
                            <div class="col-span-2">
                                <input type="text" x-model="item.code" :id="'code_' + idx"
                                       @keydown.enter.prevent.stop="lookupBarcode(idx, $event)"
                                       @keydown.tab.prevent.stop="lookupBarcode(idx, $event)"
                                       placeholder="ID / SKU"
                                       class="{{ $rowInput }} font-mono">
                            </div>
                            {{-- Name autocomplete --}}
                            <div class="relative col-span-3">
                                <input type="text" x-model="item.name" :id="'name_' + idx"
                                       @input="searchItems(idx, $event)" @focus="item.showDropdown = true"
                                       @keydown="nameKeydown(idx, $event)"
                                       @keyup="nameKeyup(idx, $event)"
                                       :readonly="nameKeyboardNavLock(item)"
                                       @click.away="item.showDropdown = false"
                                       placeholder="Search name or code…" autocomplete="off"
                                       class="{{ $rowInput }}">
                                <div x-show="item.showDropdown && item.results.length" class="combobox-options" x-cloak>
                                    <template x-for="(r, ri) in item.results" :key="r.id">
                                        <div @mousedown.prevent="pickItem(idx, r)" @mouseenter="item.activeIndex = ri"
                                             class="combobox-option" :class="{ 'active': item.activeIndex === ri }">
                                            <span class="mr-2 font-mono text-xs text-gray-400" x-text="r.code"></span>
                                            <span x-text="r.name"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            {{-- Qty --}}
                            <div class="col-span-1">
                                <input type="number" x-model.number="item.quantity" :id="'qty_' + idx"
                                       @input="recalcItem(idx)" @keydown.enter.prevent="focusField(idx, '{{ $isMove ? 'price' : 'disc' }}')"
                                       min="0" step="1"
                                       class="{{ $rowInput }} text-center"
                                       :class="isOverStock(item) ? 'border-red-400 text-red-700' : ''">
                            </div>
                            {{-- Warehouse stock (read-only) --}}
                            <div class="{{ $isMove ? 'col-span-2' : 'col-span-1' }} text-center text-xs tabular-nums"
                                 :class="isOverStock(item) ? 'font-semibold text-red-500' : 'text-gray-400'"
                                 x-text="item.item_id ? (Number(item.warehouse_stock || 0)).toLocaleString('id-ID') : '—'"></div>
                            {{-- Discount % --}}
                            @unless($isMove)
                            <div class="col-span-1">
                                <input type="number" x-model.number="item.discount" :id="'disc_' + idx"
                                       @input="recalcItem(idx)" @keydown.enter.prevent="focusField(idx, 'price')"
                                       min="0" max="100" step="0.01"
                                       class="{{ $rowInput }} text-right">
                            </div>
                            @endunless
                            {{-- Price --}}
                            <div class="col-span-2">
                                <input type="number" x-model.number="item.price" :id="'price_' + idx"
                                       @input="recalcItem(idx)" @keydown.enter.prevent="priceEnter(idx)"
                                       min="0" step="0.01"
                                       class="{{ $rowInput }} text-right">
                            </div>
                            {{-- Subtotal --}}
                            <div class="col-span-1 text-right text-sm font-medium tabular-nums" x-text="Number(item.subtotal || 0).toLocaleString('id-ID')"></div>
                            {{-- Remove --}}
                            <div class="col-span-1 text-center">
                                <button type="button" @click="removeItem(idx)"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded text-gray-400 hover:bg-red-50 hover:text-red-500">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Right column: Summary --}}
        <div class="space-y-5">
            <div class="sticky top-20 rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-gray-900">Summary</h3>
                </div>
                <div class="space-y-3 p-5">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Total Qty</span>
                        <span class="font-medium tabular-nums" x-text="form.total_quantity.toLocaleString()"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Total before Disc</span>
                        <span class="tabular-nums" x-text="'Rp ' + Number(form.gross_total).toLocaleString('id-ID')"></span>
                    </div>
                    @if($type !== 'move')
                    <div class="flex items-center justify-between text-sm">
                        <label class="text-gray-500">Discount %</label>
                        <input type="number" x-model.number="form.discount_percent" @input="recalcTotals()"
                               min="0" max="100" step="0.01"
                               class="w-20 rounded border border-gray-200 px-2 py-1 text-right text-sm focus:border-blue-500">
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <label class="text-gray-500">Adjustment</label>
                        <input type="number" x-model.number="form.adjustment" @input="recalcTotals()"
                               class="w-24 rounded border border-gray-200 px-2 py-1 text-right text-sm focus:border-blue-500">
                    </div>
                    @endif
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Total before PPN</span>
                        <span class="tabular-nums" x-text="'Rp ' + Number(form.total_before_ppn).toLocaleString('id-ID')"></span>
                    </div>
                    <div class="flex justify-between text-sm" x-show="form.ppn_amount > 0">
                        <span class="text-gray-500">PPN ({{ $ppn_rate }}%)</span>
                        <span class="tabular-nums" x-text="'Rp ' + Number(form.ppn_amount).toLocaleString('id-ID')"></span>
                    </div>
                    <div class="border-t border-gray-100 pt-3 flex justify-between">
                        <span class="font-bold text-gray-900">Grand Total</span>
                        <span class="text-lg font-bold tabular-nums text-blue-700" x-text="'Rp ' + Number(form.grand_total).toLocaleString('id-ID')"></span>
                    </div>
                </div>
                <div class="border-t border-gray-100 p-5">
                    <p x-show="!canSubmit()" x-cloak class="mb-2 text-center text-xs text-gray-400">
                        Choose sender, receiver, a valid date, and at least one complete item to save.
                    </p>
                    <button type="button" @click="submitForm()" :disabled="submitting || !canSubmit()"
                            class="w-full rounded-lg bg-blue-700 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500">
                        <span x-show="!submitting">Save {{ ucfirst($type) }} Transaction</span>
                        <span x-show="submitting">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Hidden real form (submitted programmatically) --}}
    <form id="tx-form" method="POST" action="{{ route('transactions.store') }}" style="display:none">
        @csrf
    </form>
</div>

@push('scripts')
<script>
const _PPNRate = {{ $ppn_rate }};
const _TxType  = '{{ $type }}';
const _MinDate = '{{ $min_date ?? '' }}';
const _PriceSource = @json($config['price_source'] ?? 'price');
const _Prefill = @json($prefill ?? null);
const _ItemLookupUrl = @json(route('transactions.item-by-id', ['type' => $type]));

function createTransaction() {
    const today = new Date().toISOString().split('T')[0];
    const startDate = (_MinDate && today < _MinDate) ? _MinDate : today;
    return {
        submitting: false,
        errors: {},
        serverErrors: [],
        _initialized: false,
        _barcodeFillIdx: null,
        form: {
            date: startDate,
            due_date: '',
            type: _TxType,
            sender_id: '',
            sender: null,
            receiver_id: '',
            receiver: null,
            invoice_number: '',
            note: '',
            items: [],
            discount_percent: 0,
            adjustment: 0,
            total_quantity: 0,
            gross_total: 0,
            total_before_discount: 0,
            total_before_ppn: 0,
            ppn_amount: 0,
            grand_total: 0,
        },

        init() {
            if (this._initialized) return;
            this._initialized = true;

            if (_Prefill) {
                this.form.sender_id = String(_Prefill.sender_id || '');
                this.form.sender = _Prefill.sender || null;
                this.form.receiver_id = String(_Prefill.receiver_id || '');
                this.form.receiver = _Prefill.receiver || null;
                if (_Prefill.invoice_number) this.form.invoice_number = _Prefill.invoice_number;
                if (_Prefill.note) this.form.note = _Prefill.note;
                if (_Prefill.discount_percent != null) this.form.discount_percent = Number(_Prefill.discount_percent);
                if (_Prefill.adjustment != null) this.form.adjustment = Number(_Prefill.adjustment);
                this.form.items = [];
                (_Prefill.items || []).forEach(ci => {
                    const row = this.newItemRow();
                    row.item_id = String(ci.item_id ?? ci.id ?? '');
                    row.code = ci.code || '';
                    row.name = ci.name || '';
                    row.quantity = Number(ci.quantity || 1);
                    row.price = Number(ci.price ?? ci[_PriceSource] ?? 0);
                    const gross = row.quantity * row.price;
                    row.discount = gross > 0 ? (Number(ci.discount || 0) / gross) * 100 : 0;
                    row.warehouse_items = ci.warehouse_items || [];
                    row.warehouse_stock = this.stockFor(row);
                    row.note = ci.note || '';
                    row.subtotal = gross - (gross * row.discount / 100);
                    this.form.items.push(row);
                });
                this.recalcTotals();
            }

            if (this.form.items.length === 0) {
                this.addItemRow(false);
            }
            // The warehouse side can change after items are added → refresh their stock.
            this.$watch('form.sender_id', () => this.refreshStocks());
            this.$watch('form.receiver_id', () => this.refreshStocks());
            // PPN depends on the counterparty's ppn flag.
            this.$watch('form.sender', () => this.recalcTotals());
            this.$watch('form.receiver', () => this.recalcTotals());
        },

        // Warehouse whose on-hand stock is relevant: receiver for buy/return, sender otherwise.
        get warehouseId() {
            return (_TxType === 'buy' || _TxType === 'return') ? this.form.receiver_id : this.form.sender_id;
        },

        // ---- validation (enable/disable submit, highlight rows) ----
        dateValid() {
            if (!this.form.date) return false;
            if (_MinDate && this.form.date < _MinDate) return false;
            return true;
        },
        senderValid() { return !!this.form.sender_id; },
        receiverValid() { return !!this.form.receiver_id; },
        itemStarted(i) { return !!(i.item_id || i.name || i.code); },
        itemValid(i) { return !!i.item_id && Number(i.quantity) >= 0.01 && Number(i.price) >= 0; },
        itemInvalid(i) { return this.itemStarted(i) && !this.itemValid(i); },
        validItems() { return this.form.items.filter(i => this.itemValid(i)); },
        canSubmit() {
            return this.senderValid() && this.receiverValid() && this.dateValid()
                && this.validItems().length >= 1
                && !this.form.items.some(i => this.itemInvalid(i));
        },

        newItemRow() {
            return {
                uid: Math.random().toString(36).slice(2),
                item_id: '', code: '', name: '',
                quantity: 1, price: 0, discount: 0,
                warehouse_stock: null, warehouse_items: [],
                subtotal: 0, note: '',
                results: [], showDropdown: false, activeIndex: -1, searchTimer: null,
            };
        },

        addItemRow(focus = true) {
            if (!focus && this.form.items.some(i => !i.item_id && !String(i.code || '').trim() && !String(i.name || '').trim())) {
                return;
            }
            this.form.items.push(this.newItemRow());
            if (focus) {
                const idx = this.form.items.length - 1;
                this.$nextTick(() => document.getElementById('code_' + idx)?.focus());
            }
        },

        removeItem(idx) {
            this.form.items.splice(idx, 1);
            if (this.form.items.length === 0) this.addItemRow(false);
            this.recalcTotals();
        },

        stockFor(row) {
            const wid = String(this.warehouseId || '');
            if (!wid) return 0;
            const wi = (row.warehouse_items || []).find(w => String(w.warehouse_id) === wid);
            return wi ? Number(wi.quantity || 0) : 0;
        },

        refreshStocks() {
            this.form.items.forEach(row => { if (row.item_id) row.warehouse_stock = this.stockFor(row); });
        },

        applyItemAtIndex(idx, source) {
            const row = this.form.items[idx];
            if (!row || !source) return;

            const warehouse_items = source.warehouse_items || source.warehouseItems || [];
            const updated = {
                ...row,
                item_id: String(source.id),
                code: source.code || String(source.id),
                name: source.name || '',
                price: Number(source[_PriceSource] ?? source.price ?? source.cost) || 0,
                warehouse_items,
                results: [],
                showDropdown: false,
                activeIndex: -1,
                quantity: (!row.quantity || row.quantity < 1) ? 1 : row.quantity,
            };
            updated.warehouse_stock = this.stockFor(updated);
            // Replace the row object so Alpine x-for bindings refresh reliably.
            this.form.items.splice(idx, 1, updated);
        },

        warnBarcodeNotFound(code, input) {
            alert('Barcode / ID "' + code + '" tidak ditemukan.');
            this.$nextTick(() => {
                if (input) { input.focus(); input.select?.(); }
            });
        },

        async lookupBarcode(idx, e) {
            if (!this.form.items[idx] || this._barcodeFillIdx != null) return;

            const input = e?.target ?? document.getElementById('code_' + idx);
            const code = String(input?.value ?? this.form.items[idx].code ?? '').trim();
            if (!code) return;

            this.form.items[idx].code = code;
            this._barcodeFillIdx = idx;

            try {
                const res = await fetch(`${_ItemLookupUrl}?id=${encodeURIComponent(code)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) {
                    this._barcodeFillIdx = null;
                    this.warnBarcodeNotFound(code, input);
                    return;
                }
                const data = await res.json();
                const item = data.item ?? null;
                if (item) {
                    this.applyItemAtIndex(idx, item);
                    this.recalcItem(idx);
                    this.$nextTick(() => {
                        this._barcodeFillIdx = null;
                        this.focusField(idx, 'qty');
                    });
                } else {
                    this._barcodeFillIdx = null;
                    this.warnBarcodeNotFound(code, input);
                }
            } catch (err) {
                this._barcodeFillIdx = null;
                this.warnBarcodeNotFound(code, input);
            }
        },

        searchItems(idx, e) {
            const row = this.form.items[idx];
            if (!row || this._barcodeFillIdx === idx) return;
            // Ignore programmatic x-model updates (e.g. after barcode fill).
            if (e && !e.isTrusted) return;
            row.item_id = '';
            const q = String(row.name || '').trim();
            clearTimeout(row.searchTimer);
            if (!q) { row.results = []; row.showDropdown = false; return; }
            row.searchTimer = setTimeout(async () => {
                try {
                    const res = await fetch(`/items?search=${encodeURIComponent(q)}&json=1`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    row.results = (Array.isArray(data) ? data : (data.data || [])).slice(0, 15);
                    row.activeIndex = -1;
                    row.showDropdown = row.results.length > 0;
                } catch (e) { row.results = []; }
            }, 250);
        },

        pickItem(idx, item) {
            this.applyItemAtIndex(idx, item);
            this.recalcItem(idx);
            this.$nextTick(() => this.focusField(idx, 'qty'));
        },

        nameKeyboardNavLock(row) {
            return isMobileComboboxContext() && row.showDropdown && row.results.length > 0;
        },

        nameKeydown(idx, e) {
            this.form.items[idx]._keydownHandled = false;
            if (this._processNameKey(idx, e)) {
                this.form.items[idx]._keydownHandled = true;
                e.preventDefault();
            }
        },

        nameKeyup(idx, e) {
            const row = this.form.items[idx];
            if (row._keydownHandled) return;
            if (!isMobileComboboxContext()) return;
            const key = normalizeNavigationKey(e);
            if (['ArrowDown', 'ArrowUp', 'Enter'].includes(key) && this._processNameKey(idx, e)) {
                e.preventDefault();
            }
        },

        _processNameKey(idx, e) {
            const row = this.form.items[idx];
            const key = normalizeNavigationKey(e);
            if (!key) return false;
            const len = row.results.length;

            if (this.nameKeyboardNavLock(row)) {
                if (key === 'Backspace') {
                    row.name = String(row.name || '').slice(0, -1);
                    this.searchItems(idx);
                    return true;
                }
                if (key === 'Delete') {
                    row.name = '';
                    this.searchItems(idx);
                    return true;
                }
                if (isPrintableComboboxKey(key, e)) {
                    row.name = String(row.name || '') + key;
                    row.activeIndex = -1;
                    this.searchItems(idx);
                    return true;
                }
            }

            if (key === 'ArrowDown') {
                if (len) { row.showDropdown = true; row.activeIndex = (row.activeIndex + 1) % len; }
                return true;
            }
            if (key === 'ArrowUp') {
                if (len) row.activeIndex = row.activeIndex <= 0 ? len - 1 : row.activeIndex - 1;
                return true;
            }
            if (key === 'Enter') {
                if (row.showDropdown && row.activeIndex >= 0 && row.results[row.activeIndex]) {
                    this.pickItem(idx, row.results[row.activeIndex]);
                } else if (len === 1) {
                    this.pickItem(idx, row.results[0]);
                } else {
                    this.focusField(idx, 'qty');
                }
                return true;
            }
            if (key === 'Escape') {
                row.showDropdown = false;
                return true;
            }
            return false;
        },

        focusField(idx, field) {
            this.$nextTick(() => {
                const el = document.getElementById(field + '_' + idx);
                if (el) { el.focus(); if (el.select) el.select(); }
            });
        },

        priceEnter(idx) {
            if (idx === this.form.items.length - 1) this.addItemRow(true);
            else this.focusField(idx + 1, 'code');
        },

        recalcItem(idx) {
            const item = this.form.items[idx];
            const gross = Number(item.quantity || 0) * Number(item.price || 0);
            item.subtotal = gross - (gross * Number(item.discount || 0) / 100);
            if (item.item_id) item.warehouse_stock = this.stockFor(item);
            this.recalcTotals();
        },

        recalcTotals() {
            const items = this.form.items;
            const totalQty = items.reduce((s, i) => s + Number(i.quantity || 0), 0);
            const gross = items.reduce((s, i) => s + Number(i.quantity || 0) * Number(i.price || 0), 0);
            const afterRowDisc = items.reduce((s, i) => s + Number(i.subtotal || 0), 0);
            const headerDisc = afterRowDisc * (Number(this.form.discount_percent || 0) / 100);
            const withAdj = (afterRowDisc - headerDisc) + Number(this.form.adjustment || 0);
            const contact = _TxType === 'buy' ? this.form.sender : this.form.receiver;
            const ppn = (contact?.ppn) ? withAdj * (_PPNRate / 100) : 0;

            this.form.total_quantity = totalQty;
            this.form.gross_total = gross;
            this.form.total_before_discount = afterRowDisc;
            this.form.total_before_ppn = withAdj;
            this.form.ppn_amount = ppn;
            this.form.grand_total = withAdj + ppn;
        },

        isOverStock(item) {
            if (!item.item_id) return false;
            if (!['sell','move','return-supplier','return_supplier'].includes(_TxType)) return false;
            return Number(item.quantity) > Number(item.warehouse_stock || 0);
        },

        hasStockWarning() {
            return this.form.items.some(i => this.isOverStock(i));
        },

        async uploadCSV(event) {
            const file = event.target.files[0];
            if (!file) return;
            const whId = _TxType === 'buy' || _TxType === 'return' ? this.form.receiver_id : this.form.sender_id;
            if (!whId) { alert('Please select the warehouse first before uploading CSV.'); event.target.value = ''; return; }

            const fd = new FormData();
            fd.append('csv_file', file);
            fd.append('warehouse_id', whId);
            const res = await fetch('/transactions/batch-parse', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            const data = await res.json();
            if (data.data && data.data.length > 0) {
                this.form.items = this.form.items.filter(r => r.item_id);
                data.data.forEach(ci => {
                    const row = this.newItemRow();
                    row.item_id = String(ci.item_id ?? ci.id ?? '');
                    row.code = ci.code || '';
                    row.name = ci.name || '';
                    row.quantity = Number(ci.quantity || 1);
                    row.price = Number(ci.price ?? ci[_PriceSource] ?? 0);
                    const gross = row.quantity * row.price;
                    row.discount = gross > 0 ? (Number(ci.discount || 0) / gross) * 100 : 0;
                    row.warehouse_items = ci.warehouse_items || [];
                    row.warehouse_stock = this.stockFor(row);
                    row.note = ci.note || '';
                    row.subtotal = gross - (gross * row.discount / 100);
                    this.form.items.push(row);
                });
                if (this.form.items.length === 0) this.addItemRow(false);
                this.recalcTotals();
            }
            event.target.value = '';
        },

        async submitForm() {
            if (!this.canSubmit() || this.submitting) return;
            this.submitting = true;
            this.errors = {};
            this.serverErrors = [];

            const payload = {
                date: this.form.date,
                due_date: this.form.due_date,
                type: this.form.type,
                sender_id: this.form.sender_id,
                sender_type: '{{ is_array($config['sender_type'] ?? null) ? implode(',', $config['sender_type']) : ($config['sender_type'] ?? '') }}',
                receiver_id: this.form.receiver_id,
                receiver_type: '{{ is_array($config['receiver_type'] ?? null) ? implode(',', $config['receiver_type']) : ($config['receiver_type'] ?? '') }}',
                invoice_number: this.form.invoice_number,
                note: this.form.note,
                items: this.validItems().map(i => {
                    const gross = Number(i.quantity || 0) * Number(i.price || 0);
                    return {
                        item_id: i.item_id,
                        quantity: i.quantity,
                        price: i.price,
                        discount: Number(i.discount || 0),
                        subtotal: i.subtotal,
                        warehouse_id: this.warehouseId,
                        note: i.note || '',
                    };
                }),
                discount_percent: this.form.discount_percent,
                adjustment: this.form.adjustment,
            };

            try {
                const res = await fetch('{{ route('transactions.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    redirect: 'follow',
                    body: JSON.stringify(payload),
                });

                if (res.redirected) {
                    window.location.href = res.url;
                    return;
                }

                if (res.status === 302 || res.status === 200) {
                    window.location.href = '{{ route('transactions.index') }}';
                    return;
                }

                // Validation errors — keep every input (no reload) and show what failed.
                if (res.status === 422) {
                    const err = await res.json().catch(() => ({}));
                    if (err.errors) {
                        Object.entries(err.errors).forEach(([k, v]) => {
                            this.errors[k] = Array.isArray(v) ? v[0] : v;
                        });
                        this.serverErrors = Object.values(err.errors).flat();
                    } else {
                        this.serverErrors = [err.message || 'Please fix the highlighted fields.'];
                    }
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    this.serverErrors = ['Something went wrong (HTTP ' + res.status + '). Your entries are still here — please try again.'];
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.submitting = false;
            }
        }
    };
}
</script>
@endpush
@endsection
