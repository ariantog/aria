@php
$typeTags = $typeTags ?? collect();
$sizeTags = $sizeTags ?? collect();
$warnaTags = $warnaTags ?? collect();
$jahitTags = $jahitTags ?? collect();
$showTagFilters = $showTagFilters ?? true;
$filtersStorageKey = $filtersStorageKey ?? 'aria-items-list-filters-open';
$testId = $testId ?? 'items-list-filters';
@endphp

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white" data-testid="{{ $testId }}">
    <button type="button"
            data-testid="{{ $testId }}-toggle"
            @click="filtersOpen = !filtersOpen"
            class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left text-sm font-medium text-gray-700 hover:bg-gray-50">
        <span>Filters</span>
        <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <form method="GET"
          action="{{ $formAction }}"
          x-show="filtersOpen"
          x-cloak
          class="flex flex-wrap items-end gap-2 border-t border-gray-200 p-3">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Barcode / Code</label>
            <input type="text" name="code" value="{{ $filters['code'] ?? '' }}" placeholder="ID, code, or legacy…" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Name</label>
            <input type="text" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Item or group name…" data-filter-enter-submit class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Desc</label>
            <input type="text" name="desc" value="{{ $filters['desc'] ?? '' }}" placeholder="Description…" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
        </div>
        @if($showTagFilters)
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Type</label>
            <select name="item_type" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">All</option>
                @foreach($typeTags as $t)<option value="{{ $t->id }}" @selected((string) ($filters['item_type'] ?? '') === (string) $t->id)>{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Size</label>
            <select name="size" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">All</option>
                @foreach($sizeTags as $t)<option value="{{ $t->id }}" @selected((string) ($filters['size'] ?? '') === (string) $t->id)>{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Warna</label>
            <select name="warna" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">All</option>
                @foreach($warnaTags as $t)<option value="{{ $t->id }}" @selected((string) ($filters['warna'] ?? '') === (string) $t->id)>{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Jahit</label>
            <select name="jahit" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">All</option>
                @foreach($jahitTags as $t)<option value="{{ $t->id }}" @selected((string) ($filters['jahit'] ?? '') === (string) $t->id)>{{ $t->name }}</option>@endforeach
            </select>
        </div>
        @endif
        @isset($additionalFieldsView)
            @include($additionalFieldsView, $additionalFieldsData ?? [])
        @endisset
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
            <a href="{{ $resetUrl }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
        </div>
    </form>
</div>
