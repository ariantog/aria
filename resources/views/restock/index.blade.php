@extends('layouts.app')

@section('title', 'Restock Management')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
];

$sizeType = $filters['size_type'] ?? 'alpha';
$status = $filters['status'] ?? 'restocked';
$code = $filters['code'] ?? '';

$getSafeSize = fn($s) => str_replace(['.', ' '], '_', strtolower($s));

$totalProd = $restocks->getCollection()->sum(fn($r) => (int) $r->total_prod);

// Build row payloads for Alpine bulk update
$rowPayloads = [];
foreach ($restocks as $row) {
    $rowId = ($row->group_name ?? '') . '_' . ($row->pcode ?? '') . '_' . ($row->color_name ?? '');
    $values = [];
    if (count($targetSizes) > 0) {
        foreach ($targetSizes as $size) {
            $values[$size] = (int) ($row->{'qty_' . $getSafeSize($size)} ?? 0);
        }
    } else {
        $values['default'] = (int) ($row->total_display_qty ?? 0);
    }
    $rowPayloads[$rowId] = [
        'group_id' => $row->group_id,
        'color_id' => $row->color_id,
        'pcode' => $row->pcode,
        'values' => $values,
    ];
}

$statusOptions = [
    ['label' => 'Restocked', 'value' => 'restocked'],
    ['label' => 'In Production', 'value' => 'production'],
    ['label' => 'Shipped', 'value' => 'shipped'],
    ['label' => 'Missing', 'value' => 'missing'],
];
@endphp

<div x-data="restockIndex()" class="flex flex-col gap-4 p-4">
    {{-- Stat Cards --}}
    <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100">
                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-zinc-500">Total Groups</p>
                    <p class="text-2xl font-bold text-zinc-900">{{ $restocks->total() }}</p>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100">
                    <svg class="h-5 w-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-zinc-500">Total In Production</p>
                    <p class="text-2xl font-bold text-zinc-900">{{ $totalProd }}</p>
                </div>
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-zinc-500">Received Cart</p>
                        <p class="text-2xl font-bold text-zinc-900">{{ $cartCount }}</p>
                    </div>
                </div>
                <a href="{{ route('restock.received') }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50">View Cart</a>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="flex flex-1 flex-col gap-6 rounded-xl border border-zinc-200 bg-white p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold tracking-tight text-zinc-900">Restock Registry</h2>
                <p class="text-sm text-zinc-500">Track items from restock to warehouse arrival.</p>
            </div>
            <div class="flex items-center gap-3">
                <template x-if="selectedRows.size > 0">
                    <div class="flex items-center gap-2">
                        @if($status === 'restocked')
                        <button @click="bulkAction('add_stock')" class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Add Stock</button>
                        <button @click="bulkAction('to_production')" class="rounded-md bg-orange-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-700">To Production</button>
                        @endif
                        @if($status === 'production')
                        <button @click="bulkAction('to_shipped')" class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">To Shipped</button>
                        @endif
                        @if($status === 'shipped')
                        <button @click="bulkAction('to_arrived')" class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Arrived</button>
                        <button @click="bulkAction('to_missing')" class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">To Missing</button>
                        @endif
                        @if($status !== 'shipped' && $status !== 'missing')
                        <button @click="bulkAction('to_missing')" class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">To Missing</button>
                        @endif
                    </div>
                </template>
                <a href="{{ route('restock.create') }}" class="relative inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Restock
                    @if($restockCacheCount > 0)<span class="ml-1 rounded bg-white px-1.5 text-xs text-blue-600">{{ $restockCacheCount }}</span>@endif
                </a>
                <a href="{{ route('restock.uploadExcel') }}" class="inline-flex items-center gap-2 rounded-md bg-zinc-100 px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-200">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Import
                </a>
            </div>
        </div>

        {{-- Status Tabs --}}
        <div class="border-b border-zinc-200">
            <div class="flex gap-1">
                @foreach($statusOptions as $opt)
                <a href="{{ route('restock.index', ['size_type' => $sizeType, 'status' => $opt['value'], 'code' => $code]) }}"
                   class="rounded-t-md px-4 py-2 text-sm font-medium {{ $status === $opt['value'] ? 'border-b-2 border-blue-600 text-blue-600' : 'text-zinc-500 hover:text-zinc-800' }}">{{ $opt['label'] }}</a>
                @endforeach
            </div>
        </div>

        {{-- Size Tabs + Search --}}
        <div class="flex flex-col items-center justify-between gap-4 md:flex-row">
            <div class="flex gap-1 rounded-lg bg-zinc-100 p-1">
                @foreach(['alpha' => 'Alpha Size', 'volume' => 'Volume Size', 'all' => 'All Size'] as $val => $label)
                <a href="{{ route('restock.index', ['size_type' => $val, 'status' => $status, 'code' => $code]) }}"
                   class="rounded-md px-3 py-1.5 text-sm font-medium {{ $sizeType === $val ? 'bg-white shadow-sm text-zinc-900' : 'text-zinc-500 hover:text-zinc-800' }}">{{ $label }}</a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('restock.index') }}" class="relative w-full md:w-64">
                <input type="hidden" name="size_type" value="{{ $sizeType }}">
                <input type="hidden" name="status" value="{{ $status }}">
                <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input name="code" value="{{ $code }}" placeholder="Search groups/sku..." class="h-9 w-full rounded-md border border-gray-300 bg-zinc-50 pl-9 pr-3 text-sm">
            </form>
        </div>

        {{-- Table --}}
        <div class="overflow-hidden rounded-lg border border-zinc-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50/50 text-xs uppercase text-zinc-500">
                        <tr>
                            <th class="w-10 px-4 py-3"><input type="checkbox" @change="toggleAll($event)" :checked="allChecked"></th>
                            <th class="px-6 py-3 font-bold tracking-wider">Group</th>
                            <th class="px-6 py-3 font-bold tracking-wider">SKU/Code</th>
                            <th class="px-6 py-3 font-bold tracking-wider">Color</th>
                            @if(count($targetSizes) === 0)<th class="px-6 py-3 font-bold tracking-wider">Size</th>@endif
                            @foreach($targetSizes as $size)<th class="px-4 py-3 text-center font-bold tracking-wider">{{ $size }}</th>@endforeach
                            <th class="bg-zinc-100/50 px-6 py-3 text-center font-bold tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse($restocks as $row)
                        @php $rowId = ($row->group_name ?? '') . '_' . ($row->pcode ?? '') . '_' . ($row->color_name ?? ''); @endphp
                        <tr class="transition-colors hover:bg-zinc-50" :class="isSelected('{{ $rowId }}') ? 'bg-blue-50/50' : ''">
                            <td class="px-4 py-4"><input type="checkbox" @change="toggleRow('{{ $rowId }}')" :checked="isSelected('{{ $rowId }}')"></td>
                            <td class="px-6 py-4 font-medium text-zinc-900">{{ $row->group_name ?: '-' }}</td>
                            <td class="px-6 py-4 text-zinc-600">{{ $row->pcode ?: '-' }}</td>
                            <td class="px-6 py-4">@if($row->color_name)<span class="rounded border border-zinc-300 px-2 py-0.5 text-xs">{{ $row->color_name }}</span>@else - @endif</td>
                            @if(count($targetSizes) === 0)
                            <td class="px-6 py-4 text-zinc-600">{{ $row->size_name ?? '-' }}</td>
                            <td class="px-4 py-4 text-center">
                                <template x-if="isSelected('{{ $rowId }}')">
                                    <input type="number" class="mx-auto h-8 w-20 rounded-md border border-gray-300 text-center text-sm" x-model.number="values['{{ $rowId }}']['default']">
                                </template>
                                <template x-if="!isSelected('{{ $rowId }}')">
                                    <span>@if(($row->total_display_qty ?? 0) > 0)<span class="font-mono font-semibold text-blue-600">{{ $row->total_display_qty }}</span>@else<span class="text-zinc-300">0</span>@endif</span>
                                </template>
                            </td>
                            @else
                            @foreach($targetSizes as $size)
                            @php $origVal = (int) ($row->{'qty_' . $getSafeSize($size)} ?? 0); @endphp
                            <td class="px-4 py-4 text-center">
                                <template x-if="isSelected('{{ $rowId }}')">
                                    <input type="number" max="{{ $origVal }}" class="mx-auto h-8 w-16 rounded-md border border-gray-300 text-center text-sm" x-model.number="values['{{ $rowId }}']['{{ $size }}']">
                                </template>
                                <template x-if="!isSelected('{{ $rowId }}')">
                                    <span>@if($origVal > 0)<span class="font-mono font-semibold text-blue-600">{{ $origVal }}</span>@else<span class="text-zinc-300">0</span>@endif</span>
                                </template>
                            </td>
                            @endforeach
                            @endif
                            <td class="bg-zinc-50/30 px-6 py-4 text-center">
                                <span class="rounded bg-zinc-900 px-2 py-0.5 font-mono text-white">{{ $row->total_display_qty }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ count($targetSizes) + (count($targetSizes) === 0 ? 6 : 5) }}" class="px-6 py-12 text-center text-zinc-500">No aggregated restock records found for this category.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $restocks, 'label' => 'groups'])
        </div>
    </div>
</div>

@push('scripts')
<script>
    function restockIndex() {
        return {
            status: @json($status),
            selectedRows: new Set(),
            values: {},
            rowPayloads: @json($rowPayloads),
            rowIds: @json(array_keys($rowPayloads)),
            get allChecked() {
                return this.rowIds.length > 0 && this.selectedRows.size === this.rowIds.length;
            },
            isSelected(id) {
                return this.selectedRows.has(id);
            },
            initValues(id) {
                if (!this.values[id]) {
                    this.values[id] = JSON.parse(JSON.stringify(this.rowPayloads[id].values));
                }
            },
            toggleRow(id) {
                if (this.selectedRows.has(id)) {
                    this.selectedRows.delete(id);
                } else {
                    this.initValues(id);
                    this.selectedRows.add(id);
                }
                this.selectedRows = new Set(this.selectedRows);
            },
            toggleAll(e) {
                if (e.target.checked) {
                    this.rowIds.forEach(id => { this.initValues(id); this.selectedRows.add(id); });
                } else {
                    this.selectedRows.clear();
                }
                this.selectedRows = new Set(this.selectedRows);
            },
            async bulkAction(action) {
                const today = new Date().toISOString().split('T')[0];
                const selection = Array.from(this.selectedRows).map(id => ({
                    id: id,
                    group_id: this.rowPayloads[id].group_id,
                    color_id: this.rowPayloads[id].color_id,
                    pcode: this.rowPayloads[id].pcode,
                    values: this.values[id],
                }));
                const res = await fetch(@json(route('restock.bulkUpdate')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status: this.status, action: action, selection: selection, date: today }),
                });
                if (res.ok) {
                    window.location.reload();
                } else {
                    const data = await res.json().catch(() => ({}));
                    alert(data.message || 'Bulk update failed.');
                }
            },
        };
    }
</script>
@endpush
@endsection
