@php
    $label = $label ?? 'Item Code';
    $placeholder = $placeholder ?? 'ID / SKU / name…';
    $initial = $initial ?? null;
    $endpoint = $endpoint ?? route('items.index');
@endphp

<div class="flex min-w-[220px] flex-col gap-1">
    <label class="text-xs font-medium uppercase text-gray-500">{{ $label }}</label>
    <div class="relative"
         x-data="asyncCombobox({
            endpoint: @js($endpoint),
            additionalParams: { json: '1', type: '1,2' },
            placeholder: @js($placeholder),
            initial: @js($initial),
            minChars: 1,
         })"
         x-init="init()">
        <input type="hidden" name="item_id" :value="selected ? selected.id : ''">
        <div class="relative flex h-[34px] w-full overflow-hidden rounded-md border border-gray-300 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
            <input type="text"
                   x-model="query"
                   @input="handleInput()"
                   @focus="handleFocus()"
                   @keydown="handleKeydown($event)"
                   @keyup="handleKeyup($event)"
                   :readonly="keyboardNavLock()"
                   :placeholder="placeholder"
                   class="flex-1 border-none bg-transparent px-2.5 py-1.5 text-sm outline-none placeholder-gray-400"
                   autocomplete="off"
                   data-testid="export-sell-item-combobox">
            <button type="button"
                    x-show="selected"
                    @click="clearSelection()"
                    class="flex items-center px-2 text-gray-400 hover:text-gray-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <button type="button"
                    @click="open = !open; if(!items.length) doSearch(query)"
                    class="flex items-center px-2 text-gray-400">
                <svg x-show="!loading" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                <svg x-show="loading" class="h-4 w-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </button>
        </div>
        <div x-show="open" x-cloak @click.away="open = false" class="combobox-options" x-ref="optionsList">
            <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400" x-text="emptyMessage()"></div>
            <template x-for="(item, idx) in items" :key="item.id">
                <div @click="selectItem({ ...item, name: (item.code || String(item.id)) + (item.name ? ' — ' + item.name : '') })"
                     @mouseenter="activeIndex = idx"
                     class="combobox-option"
                     :class="{ 'active': activeIndex === idx }">
                    <div class="flex flex-col text-xs">
                        <span class="font-mono font-bold" x-text="item.code || item.id"></span>
                        <span class="text-gray-500" x-text="item.name"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
