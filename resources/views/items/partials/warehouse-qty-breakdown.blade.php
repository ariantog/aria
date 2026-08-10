@props([
    'total',
    'warehouses' => [],
    'align' => 'right',
])
@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');
    $alignClass = $align === 'left' ? 'text-left' : 'text-right';
@endphp

<div class="{{ $alignClass }}">
    <div class="font-mono text-sm font-bold {{ (float) $total > 0 ? 'text-green-600' : 'text-gray-400' }}">
        {{ $fmt($total) }}
    </div>
    <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400">total all warehouses</div>
    @if(count($warehouses) > 0)
        <ul class="mt-1.5 space-y-0.5 border-t border-gray-100 pt-1.5 text-[11px] text-gray-600">
            @foreach($warehouses as $warehouse)
                <li
                    x-show="showZero || {{ (float) $warehouse['quantity'] > 0 ? 'true' : 'false' }}"
                    x-cloak
                    class="flex items-baseline justify-between gap-3 {{ $align === 'left' ? '' : 'flex-row-reverse' }}"
                >
                    <span class="truncate">{{ $warehouse['name'] }}</span>
                    <span class="shrink-0 font-mono font-semibold {{ (float) $warehouse['quantity'] > 0 ? 'text-gray-800' : 'text-gray-400' }}">
                        {{ $fmt($warehouse['quantity']) }}
                    </span>
                </li>
            @endforeach
        </ul>
        <p x-show="!showZero && {{ collect($warehouses)->contains(fn ($w) => (float) ($w['quantity'] ?? 0) > 0) ? 'false' : 'true' }}" x-cloak class="mt-1 text-[10px] italic text-gray-400">
            No stock in any warehouse.
        </p>
    @else
        <p class="mt-1 text-[10px] italic text-gray-400">No warehouse stock recorded.</p>
    @endif
</div>
