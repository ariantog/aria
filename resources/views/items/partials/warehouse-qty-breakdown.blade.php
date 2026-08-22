@props([
    'total',
    'warehouses' => [],
    'align' => 'right',
])
@php
    $alignClass = $align === 'left' ? 'text-left' : 'text-right';
@endphp

<div class="{{ $alignClass }}" x-data="{ wh: @js($warehouses) }">
    <template x-if="filteredWarehouseBreakdown(wh).mode === 'compact'">
        <div>
            <div
                class="font-mono text-sm font-bold"
                :class="filteredWarehouseBreakdown(wh).total > 0 ? 'text-green-600' : 'text-gray-400'"
                x-text="formatQty(filteredWarehouseBreakdown(wh).total)"
            ></div>
            <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400">total all warehouses</div>
        </div>
    </template>

    <template x-if="filteredWarehouseBreakdown(wh).mode === 'expanded'">
        <div>
            <div
                class="font-mono text-sm font-bold"
                :class="filteredWarehouseBreakdown(wh).total > 0 ? 'text-green-600' : 'text-gray-400'"
                x-text="formatQty(filteredWarehouseBreakdown(wh).total)"
            ></div>
            <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400">total all warehouses</div>
            <ul class="mt-1.5 space-y-0.5 border-t border-gray-100 pt-1.5 text-[11px] text-gray-600">
                <template x-for="line in filteredWarehouseBreakdown(wh).selectedLines" :key="line.name">
                    <li
                        x-show="showZero || line.quantity > 0"
                        x-cloak
                        class="flex items-baseline justify-between gap-3 {{ $align === 'left' ? '' : 'flex-row-reverse' }}"
                    >
                        <span class="truncate" x-text="line.name"></span>
                        <span
                            class="shrink-0 font-mono font-semibold"
                            :class="line.quantity > 0 ? 'text-gray-800' : 'text-gray-400'"
                            x-text="formatQty(line.quantity)"
                        ></span>
                    </li>
                </template>
                <li
                    x-show="showZero || filteredWarehouseBreakdown(wh).others > 0"
                    x-cloak
                    class="flex items-baseline justify-between gap-3 {{ $align === 'left' ? '' : 'flex-row-reverse' }}"
                >
                    <span class="truncate italic text-gray-500">Others</span>
                    <span
                        class="shrink-0 font-mono font-semibold"
                        :class="filteredWarehouseBreakdown(wh).others > 0 ? 'text-gray-800' : 'text-gray-400'"
                        x-text="formatQty(filteredWarehouseBreakdown(wh).others)"
                    ></span>
                </li>
            </ul>
        </div>
    </template>
</div>
