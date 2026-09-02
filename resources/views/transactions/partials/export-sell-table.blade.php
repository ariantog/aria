@php
    $exportBaseUrl = $exportBaseUrl ?? route('transactions.export-sell.build');
    $exportQuery = $exportQuery ?? request()->query();
    $columnLabels = app(\App\Services\ExportSellQueryService::class)->optionalTransactionColumnLabels();
    $baseColumnCount = 10;
    $optionalColumnCount = count($columnLabels);
    $totalColumnCount = $baseColumnCount + $optionalColumnCount;
@endphp

<div x-data="exportSellTable(@js($exportBaseUrl), @js($exportQuery))">
    <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
            <span class="text-[10px] font-black tracking-widest text-gray-500 uppercase">View:</span>
            @foreach($columnLabels as $columnKey => $columnLabel)
                <label class="flex cursor-pointer items-center gap-2 text-xs font-bold">
                    <input type="checkbox"
                           x-model="showTx{{ \Illuminate\Support\Str::studly($columnKey) }}"
                           class="h-4 w-4 rounded border-gray-300"
                           data-testid="toggle-export-sell-tx-{{ $columnKey }}">
                    {{ $columnLabel }}
                </label>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-2 self-end sm:self-auto">
            <button type="button"
                    @click="copyRowsTable()"
                    data-testid="copy-export-sell-table"
                    title="Copy table for Excel"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span x-text="copyFeedback ? 'Copied!' : 'Copy rows'"></span>
            </button>
            <a :href="exportUrl"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
               data-testid="export-sell-excel-link">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export Excel
            </a>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table x-ref="exportSellTable" class="w-full min-w-[1100px] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-3 py-3 font-bold" data-copy-col="date">Date</th>
                        <th class="px-3 py-3 font-bold" data-copy-col="type">Type</th>
                        <th class="px-3 py-3 font-bold" data-copy-col="invoice">Invoice</th>
                        <th class="px-3 py-3 font-bold" data-copy-col="item_id">Item ID</th>
                        <th class="px-3 py-3 font-bold" data-copy-col="item_code">Item Code</th>
                        <th class="px-3 py-3 text-right font-bold" data-copy-col="qty">Qty</th>
                        <th class="px-3 py-3 text-right font-bold" data-copy-col="item_discount">Discount</th>
                        <th class="px-3 py-3 text-right font-bold" data-copy-col="subtotal">Subtotal</th>
                        @foreach($columnLabels as $columnKey => $columnLabel)
                            <th class="px-3 py-3 font-bold {{ in_array($columnKey, ['adjustment', 'discount', 'total'], true) ? 'text-right' : '' }}"
                                data-copy-col="{{ $columnKey }}"
                                x-show="showTx{{ \Illuminate\Support\Str::studly($columnKey) }}">
                                {{ $columnLabel }}
                            </th>
                        @endforeach
                        <th class="px-3 py-3 font-bold" data-copy-col="sender">Sender</th>
                        <th class="px-3 py-3 font-bold" data-copy-col="receiver">Receiver</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php $previousTransactionId = null; @endphp
                    @forelse($rows as $row)
                        @php
                            $transactionId = (int) $row->transaction_id;
                            $isFirstLineForTransaction = $previousTransactionId !== $transactionId;
                            $previousTransactionId = $transactionId;
                            $itemUrl = \App\Http\Controllers\ExportSellController::itemShowUrl($row->item?->type, (int) $row->item_id);
                            $sender = $row->sender ?? $row->transaction?->sender;
                            $receiver = $row->receiver ?? $row->transaction?->receiver;
                            $senderUrl = \App\Http\Controllers\ExportSellController::addrbookShowUrl($sender);
                            $receiverUrl = \App\Http\Controllers\ExportSellController::addrbookShowUrl($receiver);
                            $transaction = $row->transaction;
                            $txDescription = $transaction?->description ?: ($transaction?->notes ?? '');
                        @endphp
                        <tr class="align-top hover:bg-gray-50">
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700" data-copy-col="date">
                                @if($isFirstLineForTransaction && $row->date)
                                    {{ \Illuminate\Support\Carbon::parse($row->date)->format('d M Y') }}
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2" data-copy-col="type">
                                <span class="inline-flex rounded border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-bold uppercase text-blue-700">
                                    {{ \App\Models\TransactionDetail::typeLabel((int) $row->transaction_type) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2" data-copy-col="invoice">
                                <a href="{{ route('transactions.show', $row->transaction_id) }}" class="font-mono text-blue-600 hover:underline">
                                    {{ $transaction?->invoice ?? '—' }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2" data-copy-col="item_id">
                                <a href="{{ $itemUrl }}" class="font-mono text-blue-600 hover:underline">{{ $row->item_id }}</a>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2" data-copy-col="item_code">
                                @if($row->item?->code)
                                    <a href="{{ $itemUrl }}" class="font-mono text-blue-600 hover:underline">{{ $row->item->code }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-mono" data-copy-col="qty">{{ format_amount($row->quantity) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-mono" data-copy-col="item_discount">{{ format_amount($row->discount) }}%</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-mono" data-copy-col="subtotal">{{ format_currency($row->total) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-mono" data-copy-col="adjustment" x-show="showTxAdjustment">
                                @if($isFirstLineForTransaction)
                                    <span class="{{ ($transaction?->adjustment ?? 0) < 0 ? 'text-red-600' : (($transaction?->adjustment ?? 0) > 0 ? 'text-green-600' : 'text-gray-700') }}">
                                        {{ format_currency($transaction?->adjustment ?? 0) }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-mono" data-copy-col="discount" x-show="showTxDiscount">
                                @if($isFirstLineForTransaction)
                                    {{ format_amount($transaction?->discount ?? 0) }}%
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-mono" data-copy-col="total" x-show="showTxTotal">
                                @if($isFirstLineForTransaction)
                                    {{ format_currency($transaction?->total ?? 0) }}
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="max-w-[220px] px-3 py-2 text-gray-700" data-copy-col="description" x-show="showTxDescription" @if($isFirstLineForTransaction && $txDescription !== '') title="{{ $txDescription }}" @endif>
                                @if($isFirstLineForTransaction)
                                    <span class="line-clamp-2">{{ $txDescription !== '' ? $txDescription : '—' }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2" data-copy-col="sender">
                                @if($senderUrl && $sender)
                                    <a href="{{ $senderUrl }}" class="text-blue-600 hover:underline">{{ $sender->name }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2" data-copy-col="receiver">
                                @if($receiverUrl && $receiver)
                                    <a href="{{ $receiverUrl }}" class="text-blue-600 hover:underline">{{ $receiver->name }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $totalColumnCount }}" class="px-4 py-12 text-center text-sm italic text-gray-500">No transaction lines found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $rows, 'label' => 'lines'])
    </div>
</div>

@once
    @push('scripts')
    <script>
    function exportSellTable(baseExportUrl, initialQuery) {
        const storageKey = 'aria-export-sell-view';
        const defaults = {
            showTxAdjustment: false,
            showTxDiscount: false,
            showTxTotal: false,
            showTxDescription: false,
        };
        let saved = {};

        try {
            saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
        } catch (e) {
            saved = {};
        }

        return {
            showTxAdjustment: typeof saved.showTxAdjustment === 'boolean' ? saved.showTxAdjustment : defaults.showTxAdjustment,
            showTxDiscount: typeof saved.showTxDiscount === 'boolean' ? saved.showTxDiscount : defaults.showTxDiscount,
            showTxTotal: typeof saved.showTxTotal === 'boolean' ? saved.showTxTotal : defaults.showTxTotal,
            showTxDescription: typeof saved.showTxDescription === 'boolean' ? saved.showTxDescription : defaults.showTxDescription,
            copyFeedback: false,
            copyFeedbackTimer: null,
            exportUrl: baseExportUrl,
            init() {
                this.updateExportUrl();
                this.$watch('showTxAdjustment', () => this.onViewPrefChange());
                this.$watch('showTxDiscount', () => this.onViewPrefChange());
                this.$watch('showTxTotal', () => this.onViewPrefChange());
                this.$watch('showTxDescription', () => this.onViewPrefChange());
            },
            onViewPrefChange() {
                this.persistViewPrefs();
                this.updateExportUrl();
            },
            persistViewPrefs() {
                localStorage.setItem(storageKey, JSON.stringify({
                    showTxAdjustment: this.showTxAdjustment,
                    showTxDiscount: this.showTxDiscount,
                    showTxTotal: this.showTxTotal,
                    showTxDescription: this.showTxDescription,
                }));
            },
            updateExportUrl() {
                const params = new URLSearchParams();

                Object.entries(initialQuery || {}).forEach(([key, value]) => {
                    if (value === null || value === undefined || value === '') {
                        return;
                    }

                    if (Array.isArray(value)) {
                        value.forEach((entry) => params.append(key, entry));
                        return;
                    }

                    params.set(key, value);
                });

                if (this.showTxAdjustment) {
                    params.set('show_tx_adjustment', '1');
                } else {
                    params.delete('show_tx_adjustment');
                }
                if (this.showTxDiscount) {
                    params.set('show_tx_discount', '1');
                } else {
                    params.delete('show_tx_discount');
                }
                if (this.showTxTotal) {
                    params.set('show_tx_total', '1');
                } else {
                    params.delete('show_tx_total');
                }
                if (this.showTxDescription) {
                    params.set('show_tx_description', '1');
                } else {
                    params.delete('show_tx_description');
                }

                const query = params.toString();
                this.exportUrl = query ? `${baseExportUrl}?${query}` : baseExportUrl;
            },
            showCopyFeedback() {
                this.copyFeedback = true;
                clearTimeout(this.copyFeedbackTimer);
                this.copyFeedbackTimer = setTimeout(() => {
                    this.copyFeedback = false;
                }, 2000);
            },
            isCopyColumnVisible(col) {
                if (col === 'adjustment') return this.showTxAdjustment;
                if (col === 'discount') return this.showTxDiscount;
                if (col === 'total') return this.showTxTotal;
                if (col === 'description') return this.showTxDescription;

                return true;
            },
            cellCopyValue(cell) {
                const img = cell.querySelector('img');
                if (img) {
                    return (img.getAttribute('alt') || img.getAttribute('src') || '').trim();
                }

                const link = cell.querySelector('a');
                if (link) {
                    return link.textContent.trim();
                }

                return cell.innerText.replace(/\s+/g, ' ').trim();
            },
            tableNodeToTsv(table) {
                const rows = [];

                table.querySelectorAll('thead tr, tbody tr').forEach((row) => {
                    const values = [];

                    row.querySelectorAll('[data-copy-col]').forEach((cell) => {
                        if (!this.isCopyColumnVisible(cell.dataset.copyCol)) {
                            return;
                        }

                        values.push(this.cellCopyValue(cell));
                    });

                    if (values.length) {
                        rows.push(values.join('\t'));
                    }
                });

                return rows.join('\n');
            },
            async copyRowsTable() {
                const table = this.$refs.exportSellTable;
                if (!table) {
                    return;
                }

                const clone = table.cloneNode(true);
                clone.querySelectorAll('[data-copy-col]').forEach((cell) => {
                    if (!this.isCopyColumnVisible(cell.dataset.copyCol)) {
                        cell.remove();
                    }
                });

                const plain = this.tableNodeToTsv(clone);
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
                        console.error('Failed to copy export sell table', fallbackError);
                    }
                }
            },
        };
    }
    </script>
    @endpush
@endonce
