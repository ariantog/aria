@extends('layouts.app')

@section('title', 'New ' . ucfirst($type) . ' Transaction')

@push('head-css')
<style>
    #barcode-scanner-video { width: 100%; min-height: 220px; border-radius: 0.5rem; background: #000; object-fit: cover; }
</style>
@endpush

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
                        <input type="date" x-model="form.due"
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
                                <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400" x-text="emptyMessage()"></div>
                                <template x-for="(item, idx) in items" :key="item.id">
                                    <div @click="selectItem(item)"
                                         @mouseenter="activeIndex = idx"
                                         class="combobox-option"
                                         :class="{ 'active': activeIndex === idx }">
                                        <span x-text="item.name"></span>
                                        <span x-show="item.balance !== undefined" x-text="' — Rp ' + formatAmountId(item.balance || 0)" class="ml-auto text-xs opacity-60"></span>
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
                                <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400" x-text="emptyMessage()"></div>
                                <template x-for="(item, idx) in items" :key="item.id">
                                    <div @click="selectItem(item)"
                                         @mouseenter="activeIndex = idx"
                                         class="combobox-option"
                                         :class="{ 'active': activeIndex === idx }">
                                        <span x-text="item.name"></span>
                                        <span x-show="item.balance !== undefined" x-text="' — Rp ' + formatAmountId(item.balance || 0)" class="ml-auto text-xs opacity-60"></span>
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
                        <input type="text" x-model="form.invoice" placeholder="INV-202X-XXX"
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

                {{-- Barcode lookup feedback --}}
                <div x-show="barcodeError" x-cloak data-testid="barcode-error"
                     class="mx-5 mt-4 flex items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    <span x-text="barcodeError"></span>
                    <button type="button" @click="barcodeError = ''" class="text-amber-600 hover:text-amber-800">✕</button>
                </div>

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
                    $rowInput = 'w-full h-8 min-h-8 box-border rounded border border-gray-200 px-2 py-0 text-sm leading-8 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 [-moz-appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none';
                @endphp
                <div class="divide-y divide-gray-100 px-0">
                    <template x-for="(item, idx) in form.items" :key="item.uid">
                        <div class="flex flex-col gap-3 px-5 py-4 text-sm hover:bg-gray-50 sm:grid sm:grid-cols-12 sm:items-center sm:gap-2 sm:py-2"
                             :class="(isOverStock(item) || itemInvalid(item)) ? 'bg-red-50' : ''">
                            {{-- Code / barcode --}}
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-500 sm:hidden">Code / Barcode</label>
                                <div class="flex items-center gap-1.5">
                                    <input type="text" x-model="item.code" :id="'code_' + idx"
                                           @keydown="rowKeydown(idx, 'code', $event)"
                                           @keyup="rowKeyup(idx, 'code', $event)"
                                           inputmode="text" enterkeyhint="search"
                                           placeholder="ID / SKU" autocomplete="off"
                                           class="{{ $rowInput }} min-w-0 flex-1 font-mono">
                                    <button type="button"
                                            @click="openBarcodeScanner(idx)"
                                            class="flex h-8 w-9 shrink-0 items-center justify-center rounded border border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 sm:hidden"
                                            :aria-label="'Scan barcode for row ' + (idx + 1)"
                                            data-testid="barcode-scan-btn">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                            <path stroke-linecap="round" d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2M16 4h2a1 1 0 011 1v2M20 16v2a1 1 0 01-1 1h-2"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h1M7 12h1M7 16h1M10 8h1M10 12h1M10 16h1M13 8h2M13 12h2M13 16h2M16 8h1M16 12h1M16 16h1"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            {{-- Name autocomplete --}}
                            <div class="relative sm:col-span-3">
                                <label class="mb-1 block text-xs font-medium text-gray-500 sm:hidden">Item Name</label>
                                <input type="text" x-model="item.name" :id="'name_' + idx"
                                       @input="searchItems(idx, $event)" @focus="onNameFocus(idx)"
                                       @pointerdown="onNamePointerDown(idx)"
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
                            <div class="sm:col-span-1">
                                <label class="mb-1 block text-xs font-medium text-gray-500 sm:hidden">Qty</label>
                                <input type="number" x-model.number="item.quantity" :id="'qty_' + idx"
                                       @input="recalcItem(idx)"
                                       @keydown="rowKeydown(idx, 'qty', $event)"
                                       @keyup="rowKeyup(idx, 'qty', $event)"
                                       enterkeyhint="next"
                                       min="0" step="any"
                                       class="{{ $rowInput }} text-center"
                                       :class="isOverStock(item) ? 'border-red-400 text-red-700' : ''">
                            </div>
                            {{-- Warehouse stock (read-only) --}}
                            <div class="flex items-center justify-between {{ $isMove ? 'sm:col-span-2' : 'sm:col-span-1' }} sm:block sm:text-center">
                                <span class="text-xs font-medium text-gray-500 sm:hidden">Whs. Stock</span>
                                <span class="text-xs tabular-nums"
                                      :class="isOverStock(item) ? 'font-semibold text-red-500' : 'text-gray-400'"
                                      x-text="item.item_id ? formatNumberId(item.warehouse_stock || 0) : '—'"></span>
                            </div>
                            {{-- Discount % --}}
                            @unless($isMove)
                            <div class="sm:col-span-1">
                                <label class="mb-1 block text-xs font-medium text-gray-500 sm:hidden">Disc %</label>
                                <input type="number" x-model.number="item.discount" :id="'disc_' + idx"
                                       @input="recalcItem(idx)"
                                       @keydown="rowKeydown(idx, 'disc', $event)"
                                       @keyup="rowKeyup(idx, 'disc', $event)"
                                       enterkeyhint="next"
                                       min="0" max="100" step="0.01"
                                       class="{{ $rowInput }} text-right">
                            </div>
                            @endunless
                            {{-- Price --}}
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-500 sm:hidden">Price</label>
                                <input type="number" x-model.number="item.price" :id="'price_' + idx"
                                       @input="recalcItem(idx)"
                                       @keydown="rowKeydown(idx, 'price', $event)"
                                       @keyup="rowKeyup(idx, 'price', $event)"
                                       enterkeyhint="next"
                                       min="0" step="0.01"
                                       class="{{ $rowInput }} text-right">
                            </div>
                            {{-- Subtotal --}}
                            <div class="flex items-center justify-between sm:col-span-1 sm:block sm:text-right">
                                <span class="text-xs font-medium text-gray-500 sm:hidden">Subtotal</span>
                                <span class="text-sm font-medium tabular-nums" x-text="formatAmountId(item.subtotal || 0)"></span>
                            </div>
                            {{-- Remove --}}
                            <div class="sm:col-span-1 sm:text-center">
                                <button type="button" @click="removeItem(idx)"
                                        class="inline-flex h-8 w-full items-center justify-center gap-1.5 rounded border border-red-200 text-sm text-red-600 hover:bg-red-50 sm:w-8 sm:border-0 sm:text-gray-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <span class="sm:hidden">Remove</span>
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
                        <span class="tabular-nums" x-text="'Rp ' + formatAmountId(form.gross_total)"></span>
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
                               step="any"
                               class="w-24 rounded border border-gray-200 px-2 py-1 text-right text-sm focus:border-blue-500">
                    </div>
                    @endif
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Total before PPN</span>
                        <span class="tabular-nums" x-text="'Rp ' + formatAmountId(form.total_before_ppn)"></span>
                    </div>
                    <div class="flex justify-between text-sm" x-show="form.ppn_amount > 0">
                        <span class="text-gray-500">PPN ({{ $ppn_rate }}%)</span>
                        <span class="tabular-nums" x-text="'Rp ' + formatAmountId(form.ppn_amount)"></span>
                    </div>
                    <div class="border-t border-gray-100 pt-3 flex justify-between">
                        <span class="font-bold text-gray-900">Grand Total</span>
                        <span class="text-lg font-bold tabular-nums text-blue-700" x-text="'Rp ' + formatAmountId(form.real_total)"></span>
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

    {{-- Mobile barcode scanner modal --}}
    <div x-show="scannerOpen" x-cloak
         class="fixed inset-0 z-[70] flex items-end justify-center bg-black/60 p-0 sm:items-center sm:p-4"
         @keydown.escape.window="closeBarcodeScanner()"
         data-testid="barcode-scanner-modal">
        <div class="w-full max-w-md overflow-hidden rounded-t-2xl bg-white shadow-xl sm:rounded-2xl"
             @click.outside="closeBarcodeScanner()">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-gray-900">Scan Barcode</h3>
                <button type="button" @click="closeBarcodeScanner()"
                        class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                        aria-label="Close scanner">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="space-y-3 p-4">
                <div x-show="scannerCameras.length > 1" class="flex items-center gap-2">
                    <label for="barcode-camera-select" class="shrink-0 text-xs font-medium text-gray-500">Camera</label>
                    <select id="barcode-camera-select" x-model="scannerCameraId" @change="restartBarcodeScanner()"
                            class="h-9 min-w-0 flex-1 rounded border border-gray-200 px-2 text-sm">
                        <template x-for="cam in scannerCameras" :key="cam.id">
                            <option :value="cam.id" x-text="cam.label || ('Camera ' + (scannerCameras.indexOf(cam) + 1))"></option>
                        </template>
                    </select>
                </div>
                <div class="relative overflow-hidden rounded-lg bg-black">
                    <video id="barcode-scanner-video" playsinline muted autoplay class="min-h-[220px] w-full"></video>
                    <div x-show="scannerLoading" class="absolute inset-0 flex items-center justify-center bg-black/40 text-sm text-white">
                        Opening camera…
                    </div>
                </div>
                <p x-show="scannerError" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="scannerError"></p>
                <p class="text-xs text-gray-500">Point the rear camera at a barcode. The code field will fill automatically.</p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const _PPNRate = {{ $ppn_rate }};
const _TxType  = '{{ $type }}';
const _MinDate = '{{ $min_date ?? '' }}';
const _PriceSource = @json($config['price_source'] ?? 'price');
const _Prefill = @json($prefill ?? null);
const _ItemLookupUrl = @json(route('transactions.item-by-id', ['type' => $type]));
const _ItemLookupByCodeUrl = @json(route('transactions.item-by-code', ['type' => $type]));
const _AfterQtyField = @js($isMove ? 'price' : 'disc');
const _BarcodeScannerLibUrl = 'https://cdn.jsdelivr.net/npm/@zxing/browser@0.1.5/umd/zxing-browser.min.js';

function isFrontCameraLabel(label) {
    return /front|user|selfie|facetime|true.?depth|mirror/i.test(String(label || ''));
}

function filterBackCameras(cameras) {
    const list = Array.isArray(cameras) ? cameras : [];
    const back = list.filter(c => !isFrontCameraLabel(c.label));
    return back.length ? back : list;
}

async function loadBarcodeScannerLib() {
    if (window.ZXingBrowser) return;
    await new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-barcode-scanner-lib]');
        if (existing) {
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener('error', () => reject(new Error('Failed to load barcode scanner.')), { once: true });
            return;
        }
        const script = document.createElement('script');
        script.src = _BarcodeScannerLibUrl;
        script.async = true;
        script.dataset.barcodeScannerLib = '1';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load barcode scanner.'));
        document.head.appendChild(script);
    });
}

function createTransaction() {
    const today = new Date().toISOString().split('T')[0];
    const startDate = (_MinDate && today < _MinDate) ? _MinDate : today;
    return {
        ...submitGuardFields(),
        errors: {},
        serverErrors: [],
        barcodeError: '',
        scannerOpen: false,
        scannerIdx: null,
        scannerLoading: false,
        scannerError: '',
        scannerCameraId: '',
        scannerCameras: [],
        _scannerReader: null,
        _scannerControls: null,
        _scannerHandled: false,
        _initialized: false,
        _barcodeFillIdx: null,
        _rowKeyHandled: false,
        _lastScan: { idx: -1, code: '', at: 0 },
        form: {
            date: startDate,
            due: '',
            type: _TxType,
            sender_id: '',
            sender: null,
            receiver_id: '',
            receiver: null,
            invoice: '',
            note: '',
            items: [],
            discount_percent: 0,
            adjustment: 0,
            total_quantity: 0,
            gross_total: 0,
            total_before_discount: 0,
            total_before_ppn: 0,
            ppn_amount: 0,
            real_total: 0,
        },

        init() {
            if (this._initialized) return;
            this._initialized = true;

            if (_Prefill) {
                this.form.sender_id = String(_Prefill.sender_id || '');
                this.form.sender = _Prefill.sender || null;
                this.form.receiver_id = String(_Prefill.receiver_id || '');
                this.form.receiver = _Prefill.receiver || null;
                if (_Prefill.invoice) this.form.invoice = _Prefill.invoice;
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
                    row.warehouse_item = this.warehouseItemsFrom(ci);
                    row.warehouse_stock = this.stockFor(row) || Number(ci.warehouse_stock || 0);
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
        warehouseId() {
            return (_TxType === 'buy' || _TxType === 'return') ? this.form.receiver_id : this.form.sender_id;
        },

        // Laravel JSON uses warehouse_items; item-by-id uses warehouse_item.
        warehouseItemsFrom(source) {
            const raw = source?.warehouse_item || source?.warehouseItems || source?.warehouse_items || [];
            return Array.isArray(raw) ? raw : [];
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
                warehouse_stock: null, warehouse_item: [],
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
            const wid = String(this.warehouseId() || '');
            if (!wid) return 0;
            const wi = (row.warehouse_item || []).find(w => String(w.warehouse_id) === wid);
            return wi ? Number(wi.quantity || 0) : 0;
        },

        refreshStocks() {
            this.form.items.forEach(row => { if (row.item_id) row.warehouse_stock = this.stockFor(row); });
        },

        // Mutate the existing row object; x-for scopes keep their original reference,
        // so replacing the array entry would leave the inputs bound to the stale row.
        applyItemAtIndex(idx, source) {
            const row = this.form.items[idx];
            if (!row || !source) return;

            row.item_id = String(source.id ?? source.item_id ?? '');
            row.code = source.code || source.item_code || String(source.id ?? '');
            row.name = source.name || source.product_name || '';
            row.price = Number(source[_PriceSource] ?? source.price ?? source.cost) || 0;
            row.warehouse_item = this.warehouseItemsFrom(source);
            if (!row.quantity || row.quantity < 0.01) row.quantity = 1;
            row.warehouse_stock = this.stockFor(row);
            row.results = [];
            row.showDropdown = false;
            row.activeIndex = -1;
        },

        async fetchJson(url) {
            try {
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) return null;
                return await res.json();
            } catch (_) {
                return null;
            }
        },

        // Barcode scans use the numeric item id; typed SKUs resolve through item-by-code
        // (canonical code or legacy_code, exact match, single result).
        async resolveItemByCode(code) {
            const trimmed = String(code || '').trim();
            if (!trimmed) return null;

            if (/^\d+$/.test(trimmed)) {
                const byId = await this.fetchJson(`${_ItemLookupUrl}?id=${encodeURIComponent(trimmed)}`);
                if (byId?.item) return byId.item;
            }

            const byCode = await this.fetchJson(`${_ItemLookupByCodeUrl}?code=${encodeURIComponent(trimmed)}`);
            return byCode?.item ?? null;
        },

        warnBarcodeNotFound(code, input) {
            this.barcodeError = 'Barcode / ID "' + code + '" tidak ditemukan.';
            this.$nextTick(() => {
                if (input) { input.focus(); input.select?.(); }
            });
        },

        async openBarcodeScanner(idx) {
            this.scannerIdx = idx;
            this.scannerOpen = true;
            this.scannerError = '';
            this.scannerLoading = true;
            this._scannerHandled = false;
            await this.$nextTick();
            try {
                await this.startBarcodeScanner();
            } catch (err) {
                this.scannerError = err?.message || 'Unable to open the camera.';
                this.scannerLoading = false;
            }
        },

        async startBarcodeScanner() {
            await loadBarcodeScannerLib();
            const video = document.getElementById('barcode-scanner-video');
            if (!video) throw new Error('Scanner video element missing.');

            await this.stopScannerEngine();

            // ZXing 1D reader scans the full video frame — much better for Code 128 than
            // html5-qrcode's small QR-oriented scan box / native BarcodeDetector path.
            this._scannerReader = new ZXingBrowser.BrowserMultiFormatOneDReader();

            let devices = [];
            try {
                devices = await ZXingBrowser.BrowserCodeReader.listVideoInputDevices();
            } catch (_) {
                devices = [];
            }

            const cameras = filterBackCameras(devices.map(d => ({ id: d.deviceId, label: d.label })));
            this.scannerCameras = cameras;
            if (cameras.length > 0) {
                const preferred = cameras.find(c => c.id === this.scannerCameraId) || cameras[0];
                this.scannerCameraId = preferred.id;
            } else {
                this.scannerCameraId = '';
            }

            const onResult = (result) => {
                if (result) this.handleBarcodeScan(result.getText());
            };

            try {
                if (this.scannerCameraId) {
                    this._scannerControls = await this._scannerReader.decodeFromVideoDevice(
                        this.scannerCameraId,
                        video,
                        onResult,
                    );
                } else {
                    this._scannerControls = await this._scannerReader.decodeFromConstraints(
                        { video: { facingMode: { ideal: 'environment' } } },
                        video,
                        onResult,
                    );
                }
            } catch (err) {
                if (!this.scannerCameraId) throw err;
                this._scannerControls = await this._scannerReader.decodeFromConstraints(
                    { video: { facingMode: { ideal: 'environment' } } },
                    video,
                    onResult,
                );
            }

            this.scannerLoading = false;
        },

        async restartBarcodeScanner() {
            if (!this.scannerOpen) return;
            this.scannerError = '';
            this.scannerLoading = true;
            this._scannerHandled = false;
            try {
                await this.startBarcodeScanner();
            } catch (err) {
                this.scannerError = err?.message || 'Unable to switch camera.';
                this.scannerLoading = false;
            }
        },

        handleBarcodeScan(code) {
            const trimmed = String(code || '').trim();
            if (!trimmed || this._scannerHandled) return;
            this._scannerHandled = true;

            const idx = this.scannerIdx;
            this.closeBarcodeScanner();

            const row = this.form.items[idx];
            if (!row) return;

            row.code = trimmed;
            this.$nextTick(() => {
                const input = document.getElementById('code_' + idx);
                this.lookupBarcode(idx, { target: input });
            });
        },

        async stopScannerEngine() {
            if (this._scannerControls) {
                try { this._scannerControls.stop(); } catch (_) {}
                this._scannerControls = null;
            }
            if (this._scannerReader) {
                try { this._scannerReader.reset(); } catch (_) {}
                this._scannerReader = null;
            }
            const video = document.getElementById('barcode-scanner-video');
            if (video) video.srcObject = null;
        },

        async closeBarcodeScanner() {
            this.scannerOpen = false;
            this.scannerLoading = false;
            this.scannerError = '';
            await this.stopScannerEngine();
        },

        async lookupBarcode(idx, e) {
            if (!this.form.items[idx] || this._barcodeFillIdx != null) return;

            const input = e?.target ?? document.getElementById('code_' + idx);
            const code = String(input?.value ?? this.form.items[idx].code ?? '').trim();
            if (!code) return;

            // keydown and keyup can both reach here on mobile; ignore the echo.
            const now = Date.now();
            if (this._lastScan.idx === idx && this._lastScan.code === code && now - this._lastScan.at < 700) return;
            this._lastScan = { idx, code, at: now };

            const uid = this.form.items[idx].uid;
            this.barcodeError = '';
            this._barcodeFillIdx = idx;

            try {
                const item = await this.resolveItemByCode(code);
                // Rows can shift while the request is in flight; fall back to the
                // original index when the uid is no longer present.
                const found = this.form.items.findIndex(r => r.uid === uid);
                const rowIdx = found === -1 ? idx : found;
                if (!this.form.items[rowIdx]) return;

                if (item && String(item.id ?? item.item_id ?? '') !== '') {
                    this.pickItem(rowIdx, item, true);
                } else {
                    this.warnBarcodeNotFound(code, input);
                }
            } catch (err) {
                this.warnBarcodeNotFound(code, input);
            } finally {
                this._barcodeFillIdx = null;
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
            if (!q || q.length < COMBOBOX_MIN_CHARS) { row.results = []; row.showDropdown = false; return; }
            row.searchTimer = setTimeout(async () => {
                try {
                    const res = await fetch(`/items?search=${encodeURIComponent(q)}&json=1`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    row.results = (Array.isArray(data) ? data : (data.data || [])).slice(0, COMBOBOX_MAX_RESULTS);
                    row.activeIndex = -1;
                    row.showDropdown = row.results.length > 0;
                } catch (e) { row.results = []; }
            }, 250);
        },

        // Row navigation is wired through bare keydown/keyup handlers instead of
        // Alpine's .enter/.tab modifiers: Android keyboards report key
        // "Unidentified" (keyCode 229) on keydown, which no modifier can match,
        // and sometimes only the keyup carries a usable key.
        rowKeydown(idx, field, e) {
            this._rowKeyHandled = false;
            if (this._processRowKey(idx, field, e)) {
                this._rowKeyHandled = true;
                e.preventDefault();
                e.stopPropagation();
            }
        },

        rowKeyup(idx, field, e) {
            if (this._rowKeyHandled) {
                this._rowKeyHandled = false;
                return;
            }
            if (this._processRowKey(idx, field, e)) {
                e.preventDefault();
            }
        },

        _processRowKey(idx, field, e) {
            const key = normalizeNavigationKey(e);
            const isEnter = key === 'Enter';
            // Scanners often terminate with Tab; only the code field claims it.
            const isScanTab = key === 'Tab' && field === 'code';
            if (!isEnter && !isScanTab) return false;

            const row = this.form.items[idx];
            if (!row) return false;

            if (field === 'code') {
                const code = String(e.target?.value ?? row.code ?? '').trim();
                if (!code) return isEnter;
                // Already resolved and unchanged: just advance.
                if (row.item_id && String(row.code || '') === code) {
                    this.focusField(idx, 'qty');
                    return true;
                }
                this.lookupBarcode(idx, e);
                return true;
            }

            if (field === 'qty') { this.focusField(idx, _AfterQtyField); return true; }
            if (field === 'disc') { this.focusField(idx, 'price'); return true; }
            if (field === 'price') { this.priceEnter(idx); return true; }

            return false;
        },

        pickItem(idx, item, fromBarcode = false) {
            this.barcodeError = '';
            this.applyItemAtIndex(idx, item);
            this.recalcItem(idx);
            this.$nextTick(() => this.focusField(idx, 'qty'));
        },

        nameKeyboardNavLock(row) {
            // Only lock while arrow-navigating results; locking as soon as the
            // dropdown opens blocks the mobile soft keyboard when fixing a typo.
            return isMobileComboboxContext() && row.showDropdown && row.results.length > 0 && row.activeIndex >= 0;
        },

        onNameFocus(idx) {
            const row = this.form.items[idx];
            if (!row) return;
            row.showDropdown = true;
            row.activeIndex = -1;
        },

        onNamePointerDown(idx) {
            const row = this.form.items[idx];
            if (!row || !isMobileComboboxContext()) return;
            row.activeIndex = -1;
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
            if (!row) return;
            if (row._keydownHandled) {
                row._keydownHandled = false;
                return;
            }
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
                row.activeIndex = -1;
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
            this.form.real_total = withAdj + ppn;
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
            fd.append('type', _TxType);
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
                    row.warehouse_item = this.warehouseItemsFrom(ci);
                    row.warehouse_stock = this.stockFor(row) || Number(ci.warehouse_stock || 0);
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
            if (!this.canSubmit()) return;
            if (!beginSubmit(this)) return;

            let keepLocked = false;
            this.errors = {};
            this.serverErrors = [];

            const payload = {
                date: this.form.date,
                due: this.form.due,
                type: this.form.type,
                sender_id: this.form.sender_id,
                sender_type: '{{ is_array($config['sender_type'] ?? null) ? implode(',', $config['sender_type']) : ($config['sender_type'] ?? '') }}',
                receiver_id: this.form.receiver_id,
                receiver_type: '{{ is_array($config['receiver_type'] ?? null) ? implode(',', $config['receiver_type']) : ($config['receiver_type'] ?? '') }}',
                invoice: this.form.invoice,
                note: this.form.note,
                items: this.validItems().map(i => {
                    const gross = Number(i.quantity || 0) * Number(i.price || 0);
                    return {
                        item_id: i.item_id,
                        quantity: i.quantity,
                        price: i.price,
                        discount: Number(i.discount || 0),
                        subtotal: i.subtotal,
                        warehouse_id: this.warehouseId(),
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
                        ...idempotencyHeaders(this),
                    },
                    redirect: 'follow',
                    body: JSON.stringify(payload),
                });

                if (res.redirected) {
                    keepLocked = true;
                    window.location.href = res.url;
                    return;
                }

                if (res.status === 302 || res.status === 200) {
                    keepLocked = true;
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
                this.serverErrors = ['Network error — your entries are still here. Please try again.'];
            } finally {
                if (!keepLocked) {
                    endSubmit(this);
                }
            }
        }
    };
}
</script>
@endpush
@endsection
