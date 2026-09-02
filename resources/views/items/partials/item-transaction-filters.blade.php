@php
    $defaultOpen = $defaultOpen ?? true;
    $partyLookupUrl = $partyLookupUrl ?? route('items.party-lookup');
    $filtersStorageKey = $filtersStorageKey ?? (($isAsset ?? false)
        ? 'aria-assetlancar-transaction-filters-open'
        : 'aria-items-transaction-filters-open');
@endphp

<div class="mb-4 rounded-xl border border-gray-200 bg-white"
     x-data="{
        filtersOpen: {{ $defaultOpen ? 'true' : 'false' }},
        filtersStorageKey: @js($filtersStorageKey),
        init() {
            const saved = localStorage.getItem(this.filtersStorageKey);
            this.filtersOpen = saved === null ? this.filtersOpen : saved === '1';
            this.$watch('filtersOpen', (value) => {
                localStorage.setItem(this.filtersStorageKey, value ? '1' : '0');
            });
        },
     }"
     data-testid="item-transaction-filters">
    <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-3 py-2.5">
        <span class="text-sm font-medium text-gray-700">Filters</span>
        <button type="button"
                @click="filtersOpen = !filtersOpen"
                class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-gray-600 hover:bg-gray-50"
                data-testid="toggle-item-transaction-filters">
            <span x-text="filtersOpen ? 'Hide' : 'Show'"></span>
            <svg class="h-4 w-4 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
    </div>
    <div x-show="filtersOpen" x-cloak class="p-3">
        <form method="GET" action="{{ $formAction }}" class="flex flex-wrap items-end gap-2">
            <div class="flex flex-col gap-1">
                <label for="item-tx-from" class="text-xs font-medium uppercase text-gray-500">From</label>
                <input type="date" id="item-tx-from" name="from" value="{{ $filters['from'] ?? '' }}"
                       data-testid="item-tx-from"
                       class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex flex-col gap-1">
                <label for="item-tx-to" class="text-xs font-medium uppercase text-gray-500">To</label>
                <input type="date" id="item-tx-to" name="to" value="{{ $filters['to'] ?? '' }}"
                       data-testid="item-tx-to"
                       class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex flex-col gap-1">
                <label for="item-tx-invoice" class="text-xs font-medium uppercase text-gray-500">Invoice</label>
                <input type="text" id="item-tx-invoice" name="invoice" value="{{ $filters['invoice'] ?? '' }}" placeholder="Search invoice…"
                       data-testid="item-tx-invoice"
                       class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            @include('transactions.partials.export-sell-party-combobox', [
                'name' => 'sender',
                'label' => 'Sender',
                'placeholder' => 'Search sender...',
                'endpoint' => $partyLookupUrl,
                'initial' => $selectedSender ?? null,
                'testId' => 'item-tx-sender-combobox',
            ])
            @include('transactions.partials.export-sell-party-combobox', [
                'name' => 'receiver',
                'label' => 'Receiver',
                'placeholder' => 'Search receiver...',
                'endpoint' => $partyLookupUrl,
                'initial' => $selectedReceiver ?? null,
                'testId' => 'item-tx-receiver-combobox',
            ])
            <div class="flex gap-2">
                <button type="submit" data-testid="item-tx-filter-submit"
                        class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
                <a href="{{ $resetUrl }}" data-testid="item-tx-filter-reset"
                   class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
            </div>
        </form>
    </div>
</div>
