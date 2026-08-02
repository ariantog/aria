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
                    :disabled="submitting"
                    class="flex min-w-36 items-center justify-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-60">
                <svg x-show="submitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span>Save Transaction</span>
            </button>
        </div>
    </div>

    {{-- Validation errors from server --}}
    @if($errors->any())
    <div class="rounded-lg border border-red-200 bg-red-50 p-3">
        <p class="text-sm font-medium text-red-800">Please fix the following errors:</p>
        <ul class="mt-1 list-disc pl-5 text-sm text-red-700">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

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
                            endpoint: @json($config['sender_route']),
                            placeholder: 'Select {{ $config['sender_label'] }}...',
                            onSelect: (item) => { $root.form.sender_id = item ? String(item.id) : ''; $root.form.sender = item; $root.recalcTotals(); }
                        })" class="relative">
                            <div class="relative flex h-10 w-full overflow-hidden rounded-lg border focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500"
                                 :class="$root.errors.sender_id ? 'border-red-500' : 'border-gray-300'">
                                <input type="text"
                                       x-model="query"
                                       @input="handleInput()"
                                       @focus="handleFocus()"
                                       @keydown="handleKeydown($event)"
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
                        <p x-show="$root.errors.sender_id" x-text="$root.errors.sender_id" class="mt-1 text-xs text-red-500"></p>
                    </div>

                    {{-- Receiver combobox --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ $config['receiver_label'] }} <span class="text-red-500">*</span>
                        </label>
                        <div x-data="asyncCombobox({
                            endpoint: @json($config['receiver_route']),
                            placeholder: 'Select {{ $config['receiver_label'] }}...',
                            onSelect: (item) => { $root.form.receiver_id = item ? String(item.id) : ''; $root.form.receiver = item; $root.recalcTotals(); }
                        })" class="relative">
                            <div class="relative flex h-10 w-full overflow-hidden rounded-lg border focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500"
                                 :class="$root.errors.receiver_id ? 'border-red-500' : 'border-gray-300'">
                                <input type="text"
                                       x-model="query"
                                       @input="handleInput()"
                                       @focus="handleFocus()"
                                       @keydown="handleKeydown($event)"
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
                        <p x-show="$root.errors.receiver_id" x-text="$root.errors.receiver_id" class="mt-1 text-xs text-red-500"></p>
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
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-gray-900">🛒 Line Items</h3>
                    <div class="flex gap-2">
                        @if(in_array($type, ['sell','move','buy','return','return-supplier']))
                        <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Batch CSV
                            <input type="file" accept=".csv,.txt" class="hidden" @change="uploadCSV($event)">
                        </label>
                        @endif
                        <button type="button" @click="openAddItemModal()"
                                class="flex items-center gap-1.5 rounded-lg bg-blue-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-800">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Item <span class="opacity-70">(Ctrl+I)</span>
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
                <div class="hidden grid-cols-12 gap-2 border-b bg-gray-50 px-5 py-2.5 text-xs font-medium uppercase text-gray-500 sm:grid">
                    <div class="{{ $type === 'move' ? 'col-span-5' : 'col-span-4' }}">Item</div>
                    <div class="col-span-2 text-center">Qty / Stock</div>
                    <div class="col-span-2 text-right">Price</div>
                    @if($type !== 'move')<div class="col-span-1 text-right">Disc</div>@endif
                    <div class="col-span-2 text-right">Subtotal</div>
                    <div class="col-span-1 text-center">×</div>
                </div>

                {{-- Item rows --}}
                <div class="divide-y divide-gray-100 px-0">
                    <div x-show="form.items.length === 0" class="py-12 text-center text-sm text-gray-400">
                        No items. Press <kbd class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-600">Ctrl+I</kbd> to add items.
                    </div>
                    <template x-for="(item, idx) in form.items" :key="idx">
                        <div class="grid grid-cols-12 items-center gap-2 px-5 py-3 text-sm transition-colors hover:bg-gray-50"
                             :class="isOverStock(item) ? 'bg-red-50' : ''">
                            <div :class="{{ $type === 'move' ? '\'col-span-5\'' : '\'col-span-4\'' }}" class="min-w-0">
                                <div class="font-medium text-gray-900 truncate" x-text="item.name"></div>
                                <div class="text-xs text-gray-400" x-text="item.code + (item.warehouse_name ? ' · ' + item.warehouse_name : '')"></div>
                                <div x-show="item.note" class="text-xs text-gray-400 italic" x-text="item.note"></div>
                            </div>
                            <div class="col-span-2 text-center">
                                <input type="number" x-model.number="item.quantity"
                                       @input="recalcItem(idx)"
                                       min="0" step="1"
                                       class="w-full rounded border border-gray-200 px-2 py-1 text-center text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                       :class="isOverStock(item) ? 'border-red-400 text-red-700' : ''">
                                <div x-show="item.warehouse_stock !== undefined"
                                     class="text-xs mt-0.5"
                                     :class="isOverStock(item) ? 'text-red-500 font-medium' : 'text-gray-400'"
                                     x-text="'/ ' + (item.warehouse_stock || 0)"></div>
                            </div>
                            <div class="col-span-2">
                                <input type="number" x-model.number="item.price"
                                       @input="recalcItem(idx)"
                                       min="0"
                                       class="w-full rounded border border-gray-200 px-2 py-1 text-right text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                            @if($type !== 'move')
                            <div class="col-span-1">
                                <input type="number" x-model.number="item.discount"
                                       @input="recalcItem(idx)"
                                       min="0"
                                       class="w-full rounded border border-gray-200 px-2 py-1 text-right text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                            @endif
                            <div class="col-span-2 text-right tabular-nums font-medium" x-text="Number(item.subtotal||0).toLocaleString('id-ID')"></div>
                            <div class="col-span-1 text-center">
                                <button type="button" @click="removeItem(idx)"
                                        class="flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:bg-red-50 hover:text-red-500 mx-auto">
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
                        <span class="text-gray-500">Subtotal</span>
                        <span class="tabular-nums" x-text="'Rp ' + Number(form.total_before_discount).toLocaleString('id-ID')"></span>
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
                        <span class="text-gray-500">After Disc.</span>
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
                    <button type="button" @click="submitForm()" :disabled="submitting"
                            class="w-full rounded-lg bg-blue-700 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-60">
                        <span x-show="!submitting">Save {{ ucfirst($type) }} Transaction</span>
                        <span x-show="submitting">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Item Modal --}}
    <div x-show="addItemModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         @keydown.escape.window="addItemModal = false">
        <div class="w-full max-w-lg rounded-xl border border-gray-200 bg-white shadow-xl" @click.stop>
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h3 class="font-semibold text-gray-900">Add Item</h3>
                <button @click="addItemModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5 space-y-4">
                {{-- Item search --}}
                <div x-data="asyncCombobox({
                    endpoint: '/items',
                    queryParam: 'search',
                    additionalParams: { json: true, type: '1,2' },
                    placeholder: 'Search item by name or code…',
                    onSelect: (item) => { if(item) $root.pendingItem = {...item, quantity: 1, price: Number(item[_PriceSource] ?? item.price) || 0, discount: 0 } }
                })" class="relative">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item</label>
                    <div class="relative flex h-10 overflow-hidden rounded-lg border border-gray-300 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
                        <input type="text" x-model="query"
                               @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)"
                               :placeholder="placeholder"
                               class="flex-1 border-none bg-transparent px-3 text-sm outline-none"
                               autocomplete="off" x-ref="itemInput">
                        <span x-show="loading" class="flex items-center pr-2">
                            <svg class="h-4 w-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        </span>
                    </div>
                    <div x-show="open" @click.away="open = false" class="combobox-options" x-ref="optionsList">
                        <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400">No items found.</div>
                        <template x-for="(item, idx) in items" :key="item.id">
                            <div @click="selectItem(item)" @mouseenter="activeIndex = idx"
                                 class="combobox-option" :class="{ 'active': activeIndex === idx }">
                                <span class="font-mono text-xs text-gray-400 mr-2" x-text="item.code"></span>
                                <span x-text="item.name"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <template x-if="pendingItem">
                    <div class="space-y-3 rounded-lg border border-gray-100 bg-gray-50 p-3">
                        <div class="text-sm font-medium text-gray-900" x-text="pendingItem.name"></div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Qty</label>
                                <input type="number" x-model.number="pendingItem.quantity" min="1" step="1"
                                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500">
                                <div x-show="pendingItem.warehouse_stock !== undefined" class="text-xs text-gray-400 mt-0.5" x-text="'Stock: ' + (pendingItem.warehouse_stock || 0)"></div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Price</label>
                                <input type="number" x-model.number="pendingItem.price" min="0"
                                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm text-right focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Disc (Rp)</label>
                                <input type="number" x-model.number="pendingItem.discount" min="0"
                                       class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm text-right focus:border-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Note</label>
                            <input type="text" x-model="pendingItem.note" placeholder="Optional…"
                                   class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500">
                        </div>
                    </div>
                </template>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                <button @click="addItemModal = false" type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button @click="confirmAddItem()" type="button" :disabled="!pendingItem"
                        class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-40">
                    Add to List
                </button>
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

function createTransaction() {
    const today = new Date().toISOString().split('T')[0];
    const startDate = (_MinDate && today < _MinDate) ? _MinDate : today;
    return {
        submitting: false,
        addItemModal: false,
        pendingItem: null,
        errors: {},
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
            total_before_discount: 0,
            total_before_ppn: 0,
            ppn_amount: 0,
            grand_total: 0,
        },

        init() {
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'i') {
                    e.preventDefault();
                    this.openAddItemModal();
                }
            });
        },

        openAddItemModal() {
            this.pendingItem = null;
            this.addItemModal = true;
        },

        confirmAddItem() {
            if (!this.pendingItem) return;
            const item = this.pendingItem;
            const qty = Number(item.quantity || 1);
            const price = Number(item.price || 0);
            const disc = Number(item.discount || 0);
            const subtotal = qty * price - disc;

            const existing = this.form.items.findIndex(i => String(i.item_id) === String(item.id));
            if (existing > -1) {
                const ni = { ...this.form.items[existing] };
                ni.quantity += qty;
                ni.subtotal = ni.quantity * ni.price - ni.discount;
                this.form.items[existing] = ni;
            } else {
                this.form.items.push({
                    item_id: String(item.id),
                    code: item.code,
                    name: item.name,
                    quantity: qty,
                    warehouse_id: item.warehouse_id || '',
                    warehouse_name: item.warehouse_name || 'Central',
                    warehouse_stock: item.warehouse_stock,
                    price,
                    discount: disc,
                    subtotal,
                    note: item.note || '',
                });
            }
            this.recalcTotals();
            this.addItemModal = false;
            this.pendingItem = null;
        },

        removeItem(idx) {
            this.form.items.splice(idx, 1);
            this.recalcTotals();
        },

        recalcItem(idx) {
            const item = this.form.items[idx];
            const qty = Number(item.quantity || 0);
            const price = Number(item.price || 0);
            const disc = Number(item.discount || 0);
            this.form.items[idx].subtotal = qty * price - disc;
            this.recalcTotals();
        },

        recalcTotals() {
            const items = this.form.items;
            const totalQty = items.reduce((s, i) => s + Number(i.quantity || 0), 0);
            const totalLine = items.reduce((s, i) => s + Number(i.subtotal || 0), 0);
            const discAmt = totalLine * (Number(this.form.discount_percent || 0) / 100);
            const afterDisc = totalLine - discAmt;
            const withAdj = afterDisc + Number(this.form.adjustment || 0);
            const contact = _TxType === 'buy' ? this.form.sender : this.form.receiver;
            const isPpn = contact?.ppn || false;
            const ppn = isPpn ? withAdj * (_PPNRate / 100) : 0;

            this.form.total_quantity = totalQty;
            this.form.total_before_discount = totalLine;
            this.form.total_before_ppn = withAdj;
            this.form.ppn_amount = ppn;
            this.form.grand_total = withAdj + ppn;
        },

        isOverStock(item) {
            if (!['sell','move','return_supplier'].includes(_TxType)) return false;
            return item.warehouse_stock !== undefined && Number(item.quantity) > Number(item.warehouse_stock || 0);
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
                this.form.items.push(...data.data);
                this.recalcTotals();
            }
            event.target.value = '';
        },

        async submitForm() {
            this.submitting = true;
            this.errors = {};

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
                items: this.form.items.map(i => ({
                    item_id: i.item_id,
                    quantity: i.quantity,
                    price: i.price,
                    discount: i.discount,
                    subtotal: i.subtotal,
                    warehouse_id: i.warehouse_id,
                    note: i.note,
                })),
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

                // Validation errors
                if (res.status === 422) {
                    const err = await res.json();
                    if (err.errors) {
                        Object.entries(err.errors).forEach(([k, v]) => {
                            this.errors[k] = Array.isArray(v) ? v[0] : v;
                        });
                    }
                } else {
                    const err = await res.text();
                    alert('Error: ' + res.status);
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
