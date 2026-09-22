@extends('layouts.app')
@section('title', 'Warehouse stock compare')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Warehouse stock compare', 'href' => route('reports.warehouse-compare')],
];
$buildQuery = function (array $overrides = []) use ($selectedWarehouseIds, $itemType, $sort, $perPage, $pagination) {
    $ids = $overrides['warehouse_ids'] ?? $selectedWarehouseIds;
    unset($overrides['warehouse_ids']);

    $base = [
        'warehouse_ids' => $ids,
        'item_type' => (string) $itemType->value,
        'sort' => $sort,
        'per_page' => $perPage,
    ];

    if (! array_key_exists('page', $overrides)) {
        $base['page'] = $pagination['page'] ?? 1;
    }

    return array_filter(array_merge($base, $overrides), fn ($v) => $v !== null && $v !== '' && $v !== []);
};
@endphp

<div class="flex flex-col gap-4 p-4" data-testid="warehouse-compare-page">
    <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Warehouse stock compare</h1>
            <p class="mt-1 max-w-3xl text-sm text-gray-500">
                Compare live stock across up to {{ $maxWarehouses }} warehouses. SKUs come from the
                <span class="font-medium text-gray-800">first (pivot)</span> warehouse.
                Sort and item-type filters match restock / inventory views.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('warehouse-compare-settings.edit') }}"
               class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Default settings
            </a>
            @if($pivotWarehouse)
                <a href="{{ route('reports.warehouse-compare.export', $buildQuery()) }}"
                   class="inline-flex items-center rounded-lg bg-green-700 px-3 py-2 text-sm font-medium text-white hover:bg-green-800"
                   data-testid="warehouse-compare-export">
                    Export Excel
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-medium">Please fix these errors:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <details class="rounded-xl border border-gray-200 bg-white shadow-sm group" open data-testid="warehouse-compare-controls">
        <summary class="cursor-pointer list-none px-4 py-3 text-sm font-medium text-gray-900 marker:content-none flex items-center justify-between gap-2">
            <span>Warehouses &amp; sort</span>
            <span class="text-xs font-normal text-gray-500 group-open:hidden">Show settings</span>
            <span class="text-xs font-normal text-gray-500 hidden group-open:inline">Hide settings</span>
        </summary>
        <div class="border-t border-gray-200 px-4 pb-4 pt-3">
    <form method="GET" action="{{ route('reports.warehouse-compare') }}" class="space-y-4" id="warehouse-compare-form">
        <input type="hidden" name="page" value="1">
        <div class="grid gap-4 lg:grid-cols-2">
            <div>
                <p class="mb-2 text-sm font-medium text-gray-900">Warehouses <span class="font-normal text-gray-500">(first = pivot SKU list)</span></p>
                <div class="space-y-2" id="warehouse-slots">
                    @for($i = 0; $i < $maxWarehouses; $i++)
                        @php $selectedId = $selectedWarehouseIds[$i] ?? null; @endphp
                        <div class="flex items-center gap-2">
                            <span class="w-16 shrink-0 text-xs font-medium uppercase tracking-wide text-gray-500">
                                @if($i === 0) Pivot @else #{{ $i + 1 }} @endif
                            </span>
                            <select name="warehouse_ids[]"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    data-testid="warehouse-compare-slot-{{ $i }}">
                                <option value="">— None —</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((int) $selectedId === (int) $warehouse->id)>
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <label for="warehouse-compare-sort" class="mb-1 block text-sm font-medium text-gray-700">Sort</label>
                    <select id="warehouse-compare-sort" name="sort"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        @foreach($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="warehouse-compare-per-page" class="mb-1 block text-sm font-medium text-gray-700">Page size</label>
                    <select id="warehouse-compare-per-page" name="per_page"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        @foreach($perPageOptions as $value => $label)
                            <option value="{{ $value }}" @selected((int) $perPage === (int) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="item_type" id="warehouse-compare-item-type" value="{{ $itemType->value }}">
                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                            data-testid="warehouse-compare-apply">
                        Apply
                    </button>
                    <button type="button"
                            id="warehouse-compare-copy"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            data-testid="warehouse-compare-copy">
                        Copy table
                    </button>
                </div>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('reports.warehouse-compare.save') }}" id="warehouse-compare-save-form" class="mt-3">
        @csrf
        @foreach($selectedWarehouseIds as $wid)
            <input type="hidden" name="warehouse_ids[]" value="{{ $wid }}">
        @endforeach
        <input type="hidden" name="item_type" value="{{ $itemType->value }}">
        <input type="hidden" name="sort" value="{{ $sort }}">
        <button type="submit"
                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                data-testid="warehouse-compare-save-default">
            Save current view as my default
        </button>
    </form>
        </div>
    </details>

    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Item type</span>
        @foreach($itemTypeOptions as $value => $label)
            <a href="{{ route('reports.warehouse-compare', $buildQuery(['item_type' => (string) $value, 'page' => 1])) }}"
               class="rounded-lg px-3 py-1.5 text-sm font-medium {{ (string) $itemType->value === (string) $value ? 'bg-gray-900 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}"
               data-testid="warehouse-compare-type-{{ $value }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if(! $pivotWarehouse)
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-600" data-testid="warehouse-compare-empty">
            Select a pivot warehouse and at least one comparison warehouse, then click Apply.
            You can save defaults under
            <a href="{{ route('warehouse-compare-settings.edit') }}" class="text-blue-600 hover:underline">Preferences → Warehouse compare</a>.
        </div>
    @elseif(empty($grid['blocks']))
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-600">
            No SKUs in {{ $pivotWarehouse->name }} for this item type.
        </div>
    @else
        <p class="text-sm text-gray-600">
            Pivot: <span class="font-medium text-gray-900">{{ $pivotWarehouse->name }}</span>
            · {{ count($grid['warehouses'] ?? []) }} warehouse column group(s)
            @if(($pagination['total'] ?? 0) > 0)
                · SKUs {{ number_format($pagination['from']) }}–{{ number_format($pagination['to']) }} of {{ number_format($pagination['total']) }}
            @endif
        </p>

        @if(($pagination['last_page'] ?? 1) > 1)
            <nav class="flex flex-wrap items-center gap-2 text-sm" aria-label="Pagination" data-testid="warehouse-compare-pagination">
                @if($pagination['page'] > 1)
                    <a href="{{ route('reports.warehouse-compare', $buildQuery(['page' => $pagination['page'] - 1])) }}"
                       class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50">Previous</a>
                @endif
                <span class="text-gray-600">
                    Page {{ $pagination['page'] }} / {{ $pagination['last_page'] }}
                </span>
                @if($pagination['page'] < $pagination['last_page'])
                    <a href="{{ route('reports.warehouse-compare', $buildQuery(['page' => $pagination['page'] + 1])) }}"
                       class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50">Next</a>
                @endif
            </nav>
        @endif

        @foreach($grid['blocks'] as $block)
            @include('reports.partials.warehouse-compare-table', [
                'block' => $block,
                'warehouses' => $grid['warehouses'],
                'sort' => $sort,
            ])
        @endforeach
    @endif
</div>

@push('scripts')
<script>
(function () {
    const applyForm = document.getElementById('warehouse-compare-form');
    const saveForm = document.getElementById('warehouse-compare-save-form');

    function syncSaveFormFromApply() {
        if (!applyForm || !saveForm) {
            return;
        }

        const ids = Array.from(applyForm.querySelectorAll('select[name="warehouse_ids[]"]'))
            .map(function (el) { return el.value; })
            .filter(function (v) { return v !== ''; });

        saveForm.querySelectorAll('input[name="warehouse_ids[]"]').forEach(function (el) { el.remove(); });
        ids.forEach(function (id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'warehouse_ids[]';
            input.value = id;
            saveForm.appendChild(input);
        });

        const sortEl = applyForm.querySelector('[name=sort]');
        const typeEl = applyForm.querySelector('[name=item_type]');
        if (sortEl && saveForm.querySelector('[name=sort]')) {
            saveForm.querySelector('[name=sort]').value = sortEl.value;
        }
        if (typeEl && saveForm.querySelector('[name=item_type]')) {
            saveForm.querySelector('[name=item_type]').value = typeEl.value;
        }
    }

    if (applyForm && saveForm) {
        applyForm.addEventListener('submit', syncSaveFormFromApply);
        saveForm.addEventListener('submit', syncSaveFormFromApply);
    }

    const copyBtn = document.getElementById('warehouse-compare-copy');
    if (!copyBtn) {
        return;
    }

    copyBtn.addEventListener('click', async function () {
        const tables = document.querySelectorAll('[data-warehouse-compare-table]');
        if (!tables.length) {
            alert('No table to copy.');
            return;
        }
        const chunks = [];
        tables.forEach(function (table) {
            const rows = table.querySelectorAll('tr');
            rows.forEach(function (tr) {
                const cells = tr.querySelectorAll('th, td');
                const line = Array.from(cells).map(function (cell) {
                    return (cell.innerText || '').replace(/\s+/g, ' ').trim();
                }).join('\t');
                chunks.push(line);
            });
            chunks.push('');
        });
        const text = chunks.join('\n').trim();
        try {
            await navigator.clipboard.writeText(text);
            copyBtn.textContent = 'Copied!';
            setTimeout(function () { copyBtn.textContent = 'Copy table'; }, 2000);
        } catch (e) {
            alert('Copy failed — select the table manually.');
        }
    });
})();
</script>
@endpush
@endsection
