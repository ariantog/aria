@php
    $defaultOpen = $defaultOpen ?? true;
@endphp

<div class="rounded-xl border border-gray-200 bg-white" x-data="{ showFilters: {{ $defaultOpen ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-3 py-2.5">
        <span class="text-sm font-medium text-gray-700">Filters</span>
        <button type="button"
                @click="showFilters = !showFilters"
                class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-gray-600 hover:bg-gray-50"
                data-testid="toggle-export-sell-filters">
            <span x-text="showFilters ? 'Hide' : 'Show'"></span>
            <svg class="h-4 w-4 transition-transform" :class="showFilters ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
    </div>
    <div x-show="showFilters" x-cloak class="p-3">
        <form method="GET" action="{{ $formAction }}" class="flex flex-wrap items-end gap-2">
            @include('transactions.partials.export-sell-filter-fields', [
                'filters' => $filters,
                'typeOptions' => $typeOptions,
                'selectedType' => $selectedType ?? ($filters['type'] ?? ''),
                'perPage' => $perPage ?? (int) request()->query('per_page', 100),
                'showPartyFilters' => $showPartyFilters ?? false,
                'resetUrl' => $resetUrl,
                'senderLookupUrl' => $senderLookupUrl ?? '',
                'receiverLookupUrl' => $receiverLookupUrl ?? '',
                'senderLabel' => $senderLabel ?? null,
                'receiverLabel' => $receiverLabel ?? null,
                'selectedSender' => $selectedSender ?? null,
                'selectedReceiver' => $selectedReceiver ?? null,
                'itemLookupUrl' => $itemLookupUrl ?? route('items.index'),
                'selectedItem' => $selectedItem ?? null,
            ])
        </form>
    </div>
</div>
