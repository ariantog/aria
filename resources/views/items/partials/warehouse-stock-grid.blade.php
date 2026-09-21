@props([
    'warehouseItems',
    'showZero' => 'showZero',
    'variant' => 'physical',
    'testId' => 'warehouse-availability',
])
@php
    $isDeleted = $variant === 'deleted';
    $isVirtual = $variant === 'virtual';
    $emptyLabel = match ($variant) {
        'deleted' => 'deleted',
        'virtual' => 'virtual',
        default => 'active',
    };
@endphp

<div x-data="warehouseAvailabilityTable()">
    @if($warehouseItems->isEmpty())
        <div class="py-4 text-center text-sm italic text-gray-500">No stock in {{ $emptyLabel }} warehouses.</div>
    @else
        <div class="mb-3 flex justify-end">
            <button type="button"
                    @click="copyTable()"
                    data-testid="copy-{{ $testId }}-table"
                    title="Copy table for Excel"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span x-text="copyFeedback ? 'Copied!' : 'Copy rows'"></span>
            </button>
        </div>
        <div class="overflow-x-auto">
            <table x-ref="availabilityTable" class="w-full min-w-[480px] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-3 py-3 font-bold" data-copy-col="warehouse_id">
                            <button type="button"
                                    @click="sortRows('warehouse_id')"
                                    class="inline-flex items-center gap-1 hover:text-gray-900">
                                ID
                                <span x-show="sortCol === 'warehouse_id'" class="text-blue-600" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                            </button>
                        </th>
                        <th class="px-3 py-3 font-bold" data-copy-col="warehouse_name">
                            <button type="button"
                                    @click="sortRows('warehouse_name')"
                                    class="inline-flex items-center gap-1 hover:text-gray-900">
                                Warehouse
                                <span x-show="sortCol === 'warehouse_name'" class="text-blue-600" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                            </button>
                        </th>
                        @if($isVirtual || $isDeleted)
                            <th class="px-3 py-3 font-bold" data-copy-col="status">Status</th>
                        @endif
                        <th class="px-3 py-3 text-right font-bold" data-copy-col="qty">
                            <button type="button"
                                    @click="sortRows('qty')"
                                    class="inline-flex w-full items-center justify-end gap-1 hover:text-gray-900">
                                Qty
                                <span x-show="sortCol === 'qty'" class="text-blue-600" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($warehouseItems as $wh)
                        @php
                            $qty = (float) $wh->quantity;
                            $warehouseName = $wh->warehouse?->name ?? ('Warehouse #'.$wh->warehouse_id);
                            $warehouseUrl = $wh->warehouse ? url('/'.$wh->warehouse->type_slug.'/'.$wh->warehouse->id) : null;
                        @endphp
                        <tr @class([
                            'hover:bg-gray-50',
                            'opacity-60' => $qty == 0.0,
                        ])
                            data-warehouse-id="{{ $wh->warehouse_id }}"
                            @if($qty == 0.0) x-show="{{ $showZero }}" @endif>
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-gray-700" data-copy-col="warehouse_id" data-sort-value="{{ $wh->warehouse_id }}">{{ $wh->warehouse_id }}</td>
                            <td class="px-3 py-2" data-copy-col="warehouse_name" data-sort-value="{{ $warehouseName }}">
                                @if($warehouseUrl && $variant === 'physical')
                                    <a href="{{ $warehouseUrl }}" class="font-medium text-blue-600 hover:underline">{{ $warehouseName }}</a>
                                @elseif($warehouseUrl && $isVirtual)
                                    <a href="{{ $warehouseUrl }}" class="font-medium text-violet-800 hover:underline">{{ $warehouseName }}</a>
                                @elseif($isDeleted)
                                    <span class="font-medium text-rose-800">{{ $warehouseName }}</span>
                                @else
                                    <span class="font-medium text-gray-900">{{ $warehouseName }}</span>
                                @endif
                            </td>
                            @if($isVirtual)
                                <td class="whitespace-nowrap px-3 py-2 text-[10px] font-bold uppercase tracking-wide text-violet-600" data-copy-col="status">Virtual warehouse</td>
                            @elseif($isDeleted)
                                <td class="whitespace-nowrap px-3 py-2 text-[10px] font-bold uppercase tracking-wide text-rose-600" data-copy-col="status">Deleted warehouse</td>
                            @endif
                            <td class="whitespace-nowrap px-3 py-2 text-right font-mono" data-copy-col="qty" data-copy-value="{{ format_copy_number($qty) }}" data-sort-value="{{ $qty }}">
                                <span @class([
                                    'font-bold',
                                    'text-blue-600' => $variant === 'physical' && $qty > 0,
                                    'text-violet-700' => $isVirtual && $qty > 0,
                                    'text-rose-700' => ($isDeleted && $qty > 0) || $qty < 0,
                                    'text-gray-400' => $qty == 0.0,
                                ])>{{ format_amount($qty, 0) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@once
    @push('scripts')
    <script>
    function warehouseAvailabilityTable() {
        return {
            sortCol: 'warehouse_name',
            sortDir: 'asc',
            copyFeedback: false,
            copyFeedbackTimer: null,
            showCopyFeedback() {
                this.copyFeedback = true;
                clearTimeout(this.copyFeedbackTimer);
                this.copyFeedbackTimer = setTimeout(() => {
                    this.copyFeedback = false;
                }, 2000);
            },
            sortValueForRow(row, col) {
                const cell = row.querySelector('[data-copy-col="' + col + '"]');
                if (!cell) {
                    return '';
                }

                return cell.dataset.sortValue ?? '';
            },
            compareSortValues(a, b) {
                const aNum = Number(a);
                const bNum = Number(b);
                const aIsNum = a !== '' && Number.isFinite(aNum);
                const bIsNum = b !== '' && Number.isFinite(bNum);

                if (aIsNum && bIsNum) {
                    return aNum - bNum;
                }

                return String(a).localeCompare(String(b), undefined, { sensitivity: 'base', numeric: true });
            },
            sortRows(col) {
                const table = this.$refs.availabilityTable;
                if (!table) {
                    return;
                }

                const tbody = table.querySelector('tbody');
                if (!tbody) {
                    return;
                }

                if (this.sortCol === col) {
                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortCol = col;
                    this.sortDir = 'asc';
                }

                const dir = this.sortDir === 'asc' ? 1 : -1;
                const rows = Array.from(tbody.querySelectorAll('tr'));

                rows.sort((rowA, rowB) => this.compareSortValues(
                    this.sortValueForRow(rowA, col),
                    this.sortValueForRow(rowB, col),
                ) * dir);

                rows.forEach((row) => tbody.appendChild(row));
            },
            async copyTable() {
                const table = this.$refs.availabilityTable;
                if (!table) {
                    return;
                }

                const visibleIds = new Set(
                    Array.from(table.querySelectorAll('tbody tr'))
                        .filter((row) => row.offsetParent !== null)
                        .map((row) => row.dataset.warehouseId),
                );

                const clone = ariaPrepareCopyTable(table);
                clone.querySelectorAll('tbody tr').forEach((row) => {
                    if (!visibleIds.has(row.dataset.warehouseId)) {
                        row.remove();
                    }
                });

                const plain = ariaTableToTsv(clone);
                const html = clone.outerHTML;

                try {
                    if (window.ClipboardItem && navigator.clipboard?.write) {
                        await navigator.clipboard.write([
                            new ClipboardItem({
                                'text/plain': new Blob([plain], { type: 'text/plain' }),
                                'text/html': new Blob([html], { type: 'text/html' }),
                            }),
                        ]);
                    } else {
                        await navigator.clipboard.writeText(plain);
                    }

                    this.showCopyFeedback();
                } catch (e) {
                    try {
                        await navigator.clipboard.writeText(plain);
                        this.showCopyFeedback();
                    } catch (fallbackError) {
                        console.error('Failed to copy table', fallbackError);
                    }
                }
            },
        };
    }
    </script>
    @endpush
@endonce
