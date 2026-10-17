@props([
    'warehouseItems',
    'showZero' => 'showZero',
    'variant' => 'physical',
    'testId' => 'warehouse-availability',
    'jubelioByWarehouse' => [],
    'showShopeeStock' => false,
    'shopeeStocksByWarehouse' => [],
])
@php
    $isDeleted = $variant === 'deleted';
    $isVirtual = $variant === 'virtual';
    $emptyLabel = match ($variant) {
        'deleted' => 'deleted',
        'virtual' => 'virtual',
        default => 'active',
    };
    $showJubelioColumns = $variant === 'physical' && ! empty($jubelioByWarehouse);
    $fmtJubelio = fn ($v) => $v === null ? '—' : format_amount($v, 0);
    $qtyDiffers = static function (float $ariaQty, $externalQty): bool {
        if ($externalQty === null) {
            return false;
        }

        return abs($ariaQty - (float) $externalQty) > 0.0001;
    };
    $shopeeQtyCell = function (?array $shopee, string $field, bool $highlightMismatch = false) {
        if (! $shopee || ! ($shopee['has_sync'] ?? false)) {
            return '<span class="text-gray-300">—</span>';
        }

        $value = $shopee[$field] ?? null;
        if ($value === null) {
            return '<span class="text-gray-300">—</span>';
        }

        $classes = 'font-mono tabular-nums';
        if ($highlightMismatch && ($shopee['mismatch'] ?? false)) {
            $classes .= ' font-semibold text-red-600';
        } else {
            $classes .= ' text-gray-700';
        }

        return '<span class="' . $classes . '">' . e(format_amount($value, 0)) . '</span>';
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
                                Aria qty
                                <span x-show="sortCol === 'qty'" class="text-blue-600" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                            </button>
                        </th>
                        @if($showJubelioColumns)
                            <th class="px-3 py-3 text-right font-bold" data-copy-col="jb_on_hand" title="Jubelio on hand at mapped location">JB on hand</th>
                            <th class="px-3 py-3 text-right font-bold" data-copy-col="jb_on_order" title="Jubelio on order">JB on order</th>
                            <th class="px-3 py-3 text-right font-bold" data-copy-col="jb_reserved" title="Jubelio reserved">JB rsv</th>
                            <th class="px-3 py-3 text-right font-bold" data-copy-col="jb_parity" title="Jubelio on hand + on order + reserved (matches Aria)">JB parity</th>
                            <th class="px-3 py-3 text-right font-bold" data-copy-col="jb_available" title="Jubelio available (reference)">JB avail</th>
                        @endif
                        @if($showShopeeStock && $variant === 'physical')
                            <th class="px-3 py-3 text-right font-bold" data-copy-col="sp_sellable" title="Shopee sellable">SP sell</th>
                            <th class="px-3 py-3 text-right font-bold" data-copy-col="sp_reserved" title="Shopee reserved">SP rsv</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($warehouseItems as $wh)
                        @php
                            $qty = (float) $wh->quantity;
                            $warehouseName = $wh->warehouse?->name ?? ('Warehouse #'.$wh->warehouse_id);
                            $warehouseUrl = $wh->warehouse ? url('/'.$wh->warehouse->type_slug.'/'.$wh->warehouse->id) : null;
                            $jubelio = $jubelioByWarehouse[$wh->warehouse_id] ?? null;
                            $shopee = ($shopeeStocksByWarehouse ?? [])[$wh->warehouse_id] ?? null;
                            $jubelioParity = $jubelio['parity_total'] ?? null;
                            $dualChannelMismatch = $showJubelioColumns
                                && $showShopeeStock
                                && $variant === 'physical'
                                && $jubelio !== null
                                && ($jubelio['linked'] ?? false)
                                && ($shopee['has_sync'] ?? false)
                                && $qtyDiffers($qty, $jubelioParity)
                                && $qtyDiffers($qty, $shopee['sellable'] ?? null);
                        @endphp
                        <tr @class([
                            'hover:bg-gray-50',
                            'opacity-60' => $qty == 0.0,
                        ])
                            @if($dualChannelMismatch)
                            :class="highlightDualStockMismatch ? 'bg-amber-50 ring-1 ring-inset ring-amber-200' : ''"
                            data-dual-stock-mismatch="1"
                            @endif
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
                            <td class="whitespace-nowrap px-3 py-2 text-right font-mono" data-copy-col="qty" data-sum-col="qty" data-sum-value="{{ $qty }}" data-copy-value="{{ format_copy_number($qty) }}" data-sort-value="{{ $qty }}">
                                <span @class([
                                    'font-bold',
                                    'text-blue-600' => $variant === 'physical' && $qty > 0,
                                    'text-violet-700' => $isVirtual && $qty > 0,
                                    'text-rose-700' => ($isDeleted && $qty > 0) || $qty < 0,
                                    'text-gray-400' => $qty == 0.0,
                                ])>{{ format_amount($qty, 0) }}</span>
                            </td>
                            @if($showJubelioColumns)
                                @if($jubelio)
                                    <td class="whitespace-nowrap px-3 py-2 text-right font-mono text-xs text-blue-700" data-copy-col="jb_on_hand" data-sum-col="jb_on_hand" @if($jubelio['on_hand'] !== null) data-sum-value="{{ $jubelio['on_hand'] }}" @endif data-copy-value="{{ $jubelio['on_hand'] !== null ? format_copy_number($jubelio['on_hand']) : '' }}" title="{{ $jubelio['location_name'] ?? '' }}">{{ $fmtJubelio($jubelio['on_hand']) }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right font-mono text-xs text-orange-600" data-copy-col="jb_on_order" data-sum-col="jb_on_order" @if($jubelio['on_order'] !== null) data-sum-value="{{ $jubelio['on_order'] }}" @endif data-copy-value="{{ $jubelio['on_order'] !== null ? format_copy_number($jubelio['on_order']) : '' }}">{{ $fmtJubelio($jubelio['on_order']) }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right font-mono text-xs text-violet-700" data-copy-col="jb_reserved" data-sum-col="jb_reserved" @if($jubelio['reserved'] !== null) data-sum-value="{{ $jubelio['reserved'] }}" @endif data-copy-value="{{ $jubelio['reserved'] !== null ? format_copy_number($jubelio['reserved']) : '' }}">{{ $fmtJubelio($jubelio['reserved']) }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right font-mono text-xs font-semibold {{ ($jubelio['mismatch'] ?? false) ? 'text-red-600' : 'text-green-700' }}" data-copy-col="jb_parity" data-sum-col="jb_parity" @if($jubelioParity !== null) data-sum-value="{{ $jubelioParity }}" @endif data-copy-value="{{ $jubelioParity !== null ? format_copy_number($jubelioParity) : '' }}">{{ $fmtJubelio($jubelioParity) }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right font-mono text-xs text-gray-600" data-copy-col="jb_available" data-sum-col="jb_available" @if($jubelio['available'] !== null) data-sum-value="{{ $jubelio['available'] }}" @endif data-copy-value="{{ $jubelio['available'] !== null ? format_copy_number($jubelio['available']) : '' }}">{{ $fmtJubelio($jubelio['available']) }}</td>
                                @else
                                    <td colspan="5" class="px-3 py-2 text-right text-xs text-gray-300" data-copy-col="jb_on_hand">—</td>
                                @endif
                            @endif
                            @if($showShopeeStock && $variant === 'physical')
                                <td class="whitespace-nowrap px-3 py-2 text-right text-xs" data-copy-col="sp_sellable" data-sum-col="sp_sellable" @if(($shopee['sellable'] ?? null) !== null) data-sum-value="{{ $shopee['sellable'] }}" data-copy-value="{{ format_copy_number($shopee['sellable']) }}" @endif>{!! $shopeeQtyCell($shopee, 'sellable', true) !!}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-xs" data-copy-col="sp_reserved" data-sum-col="sp_reserved" @if(($shopee['reserved'] ?? null) !== null) data-sum-value="{{ $shopee['reserved'] }}" data-copy-value="{{ format_copy_number($shopee['reserved']) }}" @endif>{!! $shopeeQtyCell($shopee, 'reserved') !!}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                @php
                    $totalLabelColspan = 2 + (($isVirtual || $isDeleted) ? 1 : 0);
                @endphp
                <tfoot class="border-t-2 border-gray-300 bg-gray-50" data-testid="{{ $testId }}-totals">
                    <tr x-show="visibleRowCount() > 0">
                        <td colspan="{{ $totalLabelColspan }}" class="px-3 py-2.5 text-right text-[10px] font-bold uppercase tracking-wider text-gray-600">Total Stok</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-sm font-bold text-blue-600" data-testid="{{ $testId }}-sum-qty" x-text="columnSum('qty')"></td>
                        @if($showJubelioColumns)
                            <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-sm font-bold text-blue-700" x-text="columnSum('jb_on_hand')"></td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-sm font-bold text-orange-600" x-text="columnSum('jb_on_order')"></td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-sm font-bold text-violet-700" x-text="columnSum('jb_reserved')"></td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-sm font-bold text-green-700" x-text="columnSum('jb_parity')"></td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-sm font-bold text-sky-700" x-text="columnSum('jb_available')"></td>
                        @endif
                        @if($showShopeeStock && $variant === 'physical')
                            <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-sm font-bold text-gray-700" x-text="columnSum('sp_sellable')"></td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-sm font-bold text-gray-600" x-text="columnSum('sp_reserved')"></td>
                        @endif
                    </tr>
                </tfoot>
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
            sumRevision: 0,
            init() {
                if (typeof this.showZero !== 'undefined') {
                    this.$watch('showZero', () => {
                        this.sumRevision++;
                    });
                }
            },
            visibleRows() {
                const table = this.$refs.availabilityTable;
                if (!table) {
                    return [];
                }

                return Array.from(table.querySelectorAll('tbody tr'))
                    .filter((row) => row.offsetParent !== null);
            },
            visibleRowCount() {
                void this.sumRevision;

                return this.visibleRows().length;
            },
            columnSum(col) {
                void this.sumRevision;

                let total = 0;
                let hasAny = false;

                for (const row of this.visibleRows()) {
                    const cell = row.querySelector('[data-sum-col="' + col + '"]');
                    if (!cell || cell.dataset.sumValue === undefined || cell.dataset.sumValue === '') {
                        continue;
                    }

                    const value = Number(cell.dataset.sumValue);
                    if (!Number.isFinite(value)) {
                        continue;
                    }

                    total += value;
                    hasAny = true;
                }

                return hasAny
                    ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(total)
                    : '—';
            },
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
                this.sumRevision++;
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
