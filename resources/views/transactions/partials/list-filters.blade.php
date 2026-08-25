@php
    $defaultOpen = $defaultOpen ?? true;
@endphp

<div class="rounded-xl border border-gray-200 bg-white" x-data="{ showFilters: {{ $defaultOpen ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-3 py-2.5">
        <span class="text-sm font-medium text-gray-700">Filters</span>
        <button type="button"
                @click="showFilters = !showFilters"
                class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-gray-600 hover:bg-gray-50"
                data-testid="toggle-transaction-filters">
            <span x-text="showFilters ? 'Hide' : 'Show'"></span>
            <svg class="h-4 w-4 transition-transform" :class="showFilters ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
    </div>
    <div x-show="showFilters" x-cloak class="p-3">
        <form method="GET" action="{{ route('transactions.index') }}" class="flex flex-wrap items-end gap-2">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                       class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                       class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">Type</label>
                <select name="type" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">All Types</option>
                    @foreach($typeMap as $id => $meta)
                        <option value="{{ $id }}" @selected(($filters['type'] ?? '') == $id)>{{ $meta[0] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">Invoice</label>
                <input type="text" name="invoice" value="{{ $filters['invoice'] ?? '' }}" placeholder="Search invoice…"
                       class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">Min Total</label>
                <input type="number" name="min_total" value="{{ $filters['min_total'] ?? '' }}" placeholder="0"
                       class="w-28 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">Max Total</label>
                <input type="number" name="max_total" value="{{ $filters['max_total'] ?? '' }}" placeholder="∞"
                       class="w-28 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">Rows / page</label>
                <select name="per_page" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    @foreach([100, 200, 300] as $size)
                        <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
                <a href="{{ route('transactions.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
            </div>
        </form>
    </div>
</div>
