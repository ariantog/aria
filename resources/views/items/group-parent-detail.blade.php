@extends('layouts.app')

@section('title', 'Group: ' . $detail['label'])

@section('content')
@php
$breadcrumbs = [
    ['title' => $detail['is_asset'] ? 'Assets' : 'Items', 'href' => $detail['is_asset'] ? '/assetlancar' : '/items'],
    ['title' => 'Groups', 'href' => route('items.group')],
    ['title' => $detail['label'], 'href' => '#'],
];
$fmt = fn ($v) => number_format((float) $v, 0, ',', '.');
@endphp

<div class="p-4 sm:p-6" x-data="groupWarehousePicker(@js($detail['warehouse_names']), @js($detail['parent_slug']))">
    <div class="mb-4">
        <a href="{{ route('items.group') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to Group List
        </a>
    </div>

    @if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white lg:col-span-1">
            <div class="flex min-h-[280px] items-center justify-center p-4">
                @if($detail['image_url'])
                    <img src="{{ $detail['image_url'] }}" alt="{{ $detail['label'] }}" class="max-h-[360px] w-auto object-contain">
                @else
                    <svg class="h-20 w-20 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                @endif
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white lg:col-span-2">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-2xl font-bold text-gray-900">{{ $detail['label'] }}</h2>
                <p class="mt-1 font-mono text-sm text-gray-500">Parent group</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 gap-x-12 gap-y-6 md:grid-cols-2">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-gray-500">Product Name</p>
                        <p class="text-xl font-medium text-gray-900">
                            {{ $detail['product_name'] }}
                            @if($detail['uses_placeholder'])
                                <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-normal text-amber-800">pcode placeholder</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-gray-500">Aria stock (physical warehouses)</p>
                        @include('items.partials.warehouse-qty-breakdown', [
                            'total' => $detail['total_warehouse_qty'],
                            'warehouses' => $detail['warehouse_breakdown'] ?? [],
                            'align' => 'left',
                        ])
                    </div>
                    @if($detail['description'])
                    <div class="border-t border-gray-100 pt-4 md:col-span-2">
                        <p class="text-sm font-semibold uppercase tracking-wider text-gray-500">Description</p>
                        <p class="leading-relaxed text-gray-700">{{ $detail['description'] }}</p>
                    </div>
                    @endif

                    @if($canEditGroup)
                    <div class="border-t border-gray-100 pt-4 md:col-span-2">
                        <p class="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">Rename Product</p>
                        <p class="mb-3 text-sm text-gray-600">Updates the product name for every color variant under this parent group.</p>
                        <form method="POST" action="{{ route('items.group-parent-update', $detail['parent_slug']) }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            @csrf
                            @method('PUT')
                            <div class="flex-1">
                                <label for="group-product-name" class="sr-only">Product name</label>
                                <input id="group-product-name" type="text" name="name"
                                       value="{{ old('name', $detail['uses_placeholder'] ? '' : $detail['product_name']) }}"
                                       placeholder="{{ $detail['label'] }}"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('name') border-red-500 @enderror">
                                @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save Product Name</button>
                        </form>
                    </div>
                    @endif

                    <div class="flex flex-wrap items-center gap-4 border-t border-gray-100 pt-4 md:col-span-2">
                        <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                            <input type="checkbox" x-model="showZero" class="rounded border-gray-300"> Show 0 Quantity
                        </label>
                        <a href="{{ route('items.group-parent-export', $detail['parent_slug']) }}"
                           class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Export Excel
                        </a>
                        @if($detail['is_asset'])
                        <a href="{{ route('restock.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50">Restock</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4 rounded-lg border border-gray-200 bg-white">
        <button
            type="button"
            class="flex w-full items-start justify-between gap-3 px-4 py-3 text-left hover:bg-gray-50"
            @click="warehouseFocusOpen = !warehouseFocusOpen"
            :aria-expanded="warehouseFocusOpen"
        >
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-semibold text-gray-900">Warehouse focus</p>
                    <span
                        class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600"
                        x-show="selectedWarehouses.length > 0"
                        x-cloak
                        x-text="selectedWarehouses.length + ' selected'"
                    ></span>
                    <span
                        class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600"
                        x-show="selectedWarehouses.length === 0"
                        x-cloak
                    >Total only</span>
                </div>
                <p class="mt-0.5 text-sm text-gray-600" x-show="!warehouseFocusOpen" x-cloak>
                    <span x-show="selectedWarehouses.length === 0">Stock tables show combined totals. Expand to pick warehouses.</span>
                    <span x-show="selectedWarehouses.length > 0" x-cloak>
                        Showing <span x-text="selectedWarehouses.join(', ')"></span> plus Others in the tables below.
                    </span>
                </p>
                <p class="mt-0.5 text-sm text-gray-600" x-show="warehouseFocusOpen" x-cloak>
                    Pick warehouses to break out in the tables below. Selection is saved in this browser.
                </p>
            </div>
            <svg
                class="mt-0.5 h-5 w-5 shrink-0 text-gray-400 transition-transform"
                :class="warehouseFocusOpen ? 'rotate-180' : ''"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div x-show="warehouseFocusOpen" x-transition x-cloak class="border-t border-gray-100 px-4 py-4">
            <div class="mb-3 flex flex-wrap justify-end gap-2" x-show="warehouseNames.length > 0" x-cloak>
                <button type="button" @click="selectAllWarehouses()" class="rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50">Select all</button>
                <button type="button" @click="clearWarehouses()" class="rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50">Clear</button>
            </div>
            @if(count($detail['warehouse_names']) > 0)
            <div class="flex flex-wrap gap-2">
                @foreach($detail['warehouse_names'] as $warehouseName)
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-1.5 text-sm transition-colors"
                       :class="isWarehouseSelected(@js($warehouseName)) ? 'border-blue-300 bg-blue-50 text-blue-800' : 'border-gray-200 bg-gray-50 text-gray-700 hover:border-gray-300'">
                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                           :checked="isWarehouseSelected(@js($warehouseName))"
                           @change="toggleWarehouse(@js($warehouseName))">
                    <span>{{ $warehouseName }}</span>
                </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-gray-500" x-show="selectedWarehouses.length === 0" x-cloak>Showing total stock only. Select one or more warehouses to see a breakdown with an Others line.</p>
            <p class="mt-2 text-xs text-blue-700" x-show="selectedWarehouses.length > 0" x-cloak>
                Showing <span x-text="selectedWarehouses.length"></span> selected warehouse<span x-show="selectedWarehouses.length !== 1" x-cloak>s</span> plus Others.
            </p>
            @else
            <p class="text-sm italic text-gray-500">No warehouse stock recorded for this group.</p>
            @endif
        </div>
    </div>

    <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        <p class="font-medium">How to read quantities</p>
        <ul class="mt-1 list-inside list-disc space-y-0.5 text-blue-800">
            <li><strong>Aria</strong> — physical stock in each Core warehouse (summed per SKU, then per color / parent).</li>
            <li><strong>Jubelio</strong> — online inventory from Jubelio (all channels combined, not per warehouse).</li>
        </ul>
    </div>

    <h2 class="mb-4 text-xl font-bold text-gray-900">Colors &amp; Sizes</h2>

    <div class="space-y-8">
        @foreach($detail['colors'] as $color)
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b-4 border-purple-300 bg-purple-50 px-6 py-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <h3 class="text-lg font-bold uppercase tracking-wide text-purple-900">{{ $color['code'] }}</h3>
                        <span class="text-sm text-purple-700">{{ $color['name'] }}</span>
                        @if($color['pcode'])
                        <span class="font-mono text-xs text-purple-500">{{ $color['pcode'] }}</span>
                        @endif
                    </div>
                    <div class="min-w-[10rem] rounded-lg border border-purple-200 bg-white/80 px-3 py-2">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-purple-700">Color total (Aria)</p>
                        @include('items.partials.warehouse-qty-breakdown', [
                            'total' => $color['in_warehouse_qty'] ?? 0,
                            'warehouses' => $color['warehouse_breakdown'] ?? [],
                            'align' => 'left',
                        ])
                    </div>
                </div>
            </div>

            <div class="p-4">
                @if($color['has_sizes'])
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Size</th>
                                <th class="px-4 py-3 text-left font-semibold">SKU</th>
                                <th class="px-4 py-3 text-right font-semibold">
                                    <div>Aria stock</div>
                                    <div class="mt-0.5 text-[10px] font-normal normal-case text-gray-400" x-text="selectedWarehouses.length > 0 ? 'selected + others' : 'all warehouses total'"></div>
                                </th>
                                <th class="px-4 py-3 text-right font-semibold">
                                    <div>Jubelio on hand</div>
                                    <div class="mt-0.5 text-[10px] font-normal normal-case text-gray-400">all channels</div>
                                </th>
                                <th class="px-4 py-3 text-right font-semibold">
                                    <div>On order</div>
                                    <div class="mt-0.5 text-[10px] font-normal normal-case text-gray-400">Jubelio</div>
                                </th>
                                <th class="px-4 py-3 text-right font-semibold">
                                    <div>Reserved</div>
                                    <div class="mt-0.5 text-[10px] font-normal normal-case text-gray-400">Jubelio</div>
                                </th>
                                <th class="px-4 py-3 text-right font-semibold">
                                    <div>Available</div>
                                    <div class="mt-0.5 text-[10px] font-normal normal-case text-gray-400">Jubelio</div>
                                </th>
                                <th class="px-4 py-3 text-right font-semibold"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($color['size_rows'] as $row)
                            <tr class="hover:bg-gray-50/50" x-show="showZero || {{ (float) $row['warehouse_qty'] > 0 ? 'true' : 'false' }}" x-cloak>
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ $row['size'] }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ $row['show_url'] }}" class="font-mono text-blue-600 hover:underline">{{ $row['code'] }}</a>
                                    <div class="text-xs text-gray-500">{{ $row['name'] }}</div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    @include('items.partials.warehouse-qty-breakdown', [
                                        'total' => $row['warehouse_qty'],
                                        'warehouses' => $row['warehouses'],
                                    ])
                                </td>
                                <td class="px-4 py-3 text-right font-mono {{ $row['jubelio']['linked'] ? 'text-blue-600' : 'text-gray-300' }}">
                                    {{ $row['jubelio']['linked'] ? $fmt($row['jubelio']['on_hand']) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-orange-600">
                                    {{ $row['jubelio']['linked'] ? $fmt($row['jubelio']['on_order']) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-gray-600">
                                    {{ $row['jubelio']['linked'] ? $fmt($row['jubelio']['reserved']) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-green-600">
                                    {{ $row['jubelio']['linked'] ? $fmt($row['jubelio']['available']) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ $row['show_url'] }}/edit" class="text-xs text-gray-500 hover:text-gray-800">Edit</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="space-y-4">
                    @foreach($color['no_size_items'] as $row)
                    <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-4" x-show="showZero || {{ (float) $row['warehouse_qty'] > 0 ? 'true' : 'false' }}" x-cloak>
                        <div class="mb-3 flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <a href="{{ $row['show_url'] }}" class="font-mono font-semibold text-blue-600 hover:underline">{{ $row['code'] }}</a>
                                <p class="text-sm text-gray-600">{{ $row['name'] }}</p>
                            </div>
                            <div class="min-w-[10rem] rounded-lg border border-gray-200 bg-white px-3 py-2">
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500">Aria stock</p>
                                @include('items.partials.warehouse-qty-breakdown', [
                                    'total' => $row['warehouse_qty'],
                                    'warehouses' => $row['warehouses'],
                                    'align' => 'left',
                                ])
                            </div>
                            <div class="text-right text-sm">
                                <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Jubelio available</div>
                                <div class="text-[10px] text-gray-400">all channels</div>
                                <div class="font-mono font-bold {{ $row['jubelio']['linked'] ? 'text-green-600' : 'text-gray-300' }}">
                                    {{ $row['jubelio']['linked'] ? $fmt($row['jubelio']['available']) : '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </section>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
function groupWarehousePicker(warehouseNames, parentSlug) {
    return {
        showZero: false,
        warehouseFocusOpen: false,
        warehouseNames,
        selectedWarehouses: [],
        storageKey: 'aria-item-group-wh-' + parentSlug,
        openStorageKey: 'aria-item-group-wh-open-' + parentSlug,
        init() {
            try {
                const saved = JSON.parse(localStorage.getItem(this.storageKey) || '[]');
                if (Array.isArray(saved)) {
                    this.selectedWarehouses = saved.filter((name) => this.warehouseNames.includes(name));
                }
            } catch (e) {
                this.selectedWarehouses = [];
            }

            const savedOpen = localStorage.getItem(this.openStorageKey);
            this.warehouseFocusOpen = savedOpen === '1';

            this.$watch('selectedWarehouses', (value) => {
                localStorage.setItem(this.storageKey, JSON.stringify(value));
            });

            this.$watch('warehouseFocusOpen', (value) => {
                localStorage.setItem(this.openStorageKey, value ? '1' : '0');
            });
        },
        isWarehouseSelected(name) {
            return this.selectedWarehouses.includes(name);
        },
        toggleWarehouse(name) {
            const index = this.selectedWarehouses.indexOf(name);
            if (index >= 0) {
                this.selectedWarehouses.splice(index, 1);
            } else {
                this.selectedWarehouses.push(name);
            }
        },
        selectAllWarehouses() {
            this.selectedWarehouses = [...this.warehouseNames];
        },
        clearWarehouses() {
            this.selectedWarehouses = [];
        },
        formatQty(value) {
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.round(Number(value) || 0));
        },
        filteredWarehouseBreakdown(warehouses) {
            const lines = Array.isArray(warehouses) ? warehouses : [];
            const total = lines.reduce((sum, line) => sum + Number(line.quantity || 0), 0);

            if (this.selectedWarehouses.length === 0) {
                return { mode: 'compact', total, selectedLines: [], others: 0 };
            }

            const selectedSet = new Set(this.selectedWarehouses);
            const selectedLines = [];
            let others = 0;

            for (const line of lines) {
                if (selectedSet.has(line.name)) {
                    selectedLines.push(line);
                } else {
                    others += Number(line.quantity || 0);
                }
            }

            for (const name of this.selectedWarehouses) {
                if (!selectedLines.some((line) => line.name === name)) {
                    selectedLines.push({ name, quantity: 0 });
                }
            }

            selectedLines.sort((a, b) => a.name.localeCompare(b.name));

            return { mode: 'expanded', total, selectedLines, others };
        },
    };
}
</script>
@endpush
@endsection
