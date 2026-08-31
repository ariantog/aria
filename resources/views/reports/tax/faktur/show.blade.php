@extends('layouts.app')

@section('title', 'Faktur '.$import->faktur_number)

@section('content')
@php
$fmt = fn ($v) => format_amount($v);
$monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$lineItems = $import->line_items ?? [];
$gross = $import->fakturGross();
@endphp

<div class="flex flex-col gap-4 p-4">
    <div>
        <a href="{{ route('reports.tax.faktur.index') }}" class="text-sm text-blue-600 hover:underline">← Kembali ke daftar faktur</a>
        <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Faktur {{ $import->faktur_number }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $import->directionLabel() }} — {{ $monthNames[$import->report_month] ?? $import->report_month }} {{ $import->report_year }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($hasPdf)
                    <a href="{{ route('reports.tax.faktur.pdf', $import) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-testid="faktur-download-pdf">
                        Download PDF
                    </a>
                @endif
                @if($canImport)
                    <a href="{{ route('reports.tax.faktur.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Upload baru</a>
                @endif
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-sm">
            <h3 class="mb-3 font-semibold text-gray-900">Data faktur</h3>
            <dl class="space-y-2">
                <div><dt class="text-gray-500">Tanggal faktur</dt><dd class="tabular-nums">{{ $import->faktur_date?->format('Y-m-d') }} @if($import->faktur_date_place)<span class="text-xs text-gray-400">({{ $import->faktur_date_place }})</span>@endif</dd></div>
                <div><dt class="text-gray-500">Penjual (PKP)</dt><dd>{{ $import->seller_name }} <span class="text-xs text-gray-400">{{ $import->seller_npwp }}</span></dd></div>
                <div><dt class="text-gray-500">Pembeli</dt><dd>{{ $import->buyer_name }} <span class="text-xs text-gray-400">{{ $import->buyer_npwp }}</span></dd></div>
                <div><dt class="text-gray-500">Reporting entity</dt><dd>{{ $import->reportingEntity?->name }}</dd></div>
                <div><dt class="text-gray-500">Lawan transaksi</dt><dd>{{ $import->counterparty?->name }}</dd></div>
                <div><dt class="text-gray-500">DPP / PPN / PPnBM</dt><dd class="tabular-nums">{{ $fmt($import->dpp) }} / {{ $fmt($import->ppn) }} / {{ $fmt($import->ppnbm) }}</dd></div>
                <div><dt class="text-gray-500">Gross faktur (DPP+PPN) — dasar Sell</dt><dd class="tabular-nums font-medium">{{ $fmt($gross) }}</dd></div>
                @if($import->signatory_name)
                    <div><dt class="text-gray-500">Penandatangan</dt><dd>{{ $import->signatory_name }}</dd></div>
                @endif
                @if($import->notes)
                    <div><dt class="text-gray-500">Catatan</dt><dd>{{ $import->notes }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-sm">
            <h3 class="mb-3 font-semibold text-gray-900">Pembayaran & import</h3>
            <dl class="space-y-2">
                <div>
                    <dt class="text-gray-500">Jatuh tempo (perkiraan)</dt>
                    <dd>
                        @if($import->expected_payment_date)
                            <span class="tabular-nums">{{ $import->expected_payment_date->format('Y-m-d') }}</span>
                            @if($import->isPaymentOverdue())
                                <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-900">Terlambat</span>
                            @endif
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Pembayaran diterima (nett + PPN)</dt>
                    <dd>
                        @if($import->payment_received_date)
                            <span class="tabular-nums text-green-700">{{ $fmt($import->payment_received_amount) }}</span>
                            <span class="text-gray-500"> pada {{ $import->payment_received_date->format('Y-m-d') }}</span>
                        @else
                            <span class="text-gray-400">Belum dibayar</span>
                        @endif
                    </dd>
                </div>
                @if($import->payment_variance && abs((float) $import->payment_variance) > 0.01)
                    <div>
                        <dt class="text-gray-500">Margin / biaya konsinyasi (gross − bayar)</dt>
                        <dd class="tabular-nums">{{ $fmt(abs((float) $import->payment_variance)) }}
                            @if((float) $import->payment_variance < 0)
                                <span class="text-xs text-gray-500">(MDS/Central potong)</span>
                            @endif
                            @if($import->varianceExpenseAccount)
                                <span class="text-xs text-gray-500">→ {{ $import->varianceExpenseAccount->name }}</span>
                            @endif
                            @if($import->varianceTransaction)
                                <a href="{{ route('transactions.show', $import->varianceTransaction) }}" class="ml-1 text-xs text-blue-600 hover:underline">Tx #{{ $import->varianceTransaction->id }}</a>
                            @endif
                        </dd>
                    </div>
                @endif
                <div>
                    <dt class="text-gray-500">Cash In terkait</dt>
                    <dd>
                        @if($import->cashInTransaction)
                            <a href="{{ route('transactions.show', $import->cashInTransaction) }}" class="text-blue-600 hover:underline">
                                #{{ $import->cashInTransaction->id }}
                            </a>
                            <span class="text-gray-500"> · {{ $import->cashInTransaction->date?->format('Y-m-d') }} · {{ $fmt(abs((float) $import->cashInTransaction->total)) }}</span>
                        @else
                            <span class="text-gray-400">Belum di-link</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Sell terkait</dt>
                    <dd>
                        @if($import->sellTransaction)
                            <a href="{{ route('transactions.show', $import->sellTransaction) }}" class="text-blue-600 hover:underline">
                                #{{ $import->sellTransaction->id }}
                            </a>
                            <span class="text-gray-500"> · {{ $import->sellTransaction->date?->format('Y-m-d') }} · Gross {{ $fmt(abs((float) $import->sellTransaction->real_total)) }} (DPP {{ $fmt(abs((float) $import->sellTransaction->total)) }} + PPN {{ $fmt((float) $import->sellTransaction->ppn) }})</span>
                        @else
                            <span class="text-gray-400">Belum di-post</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Diimport</dt>
                    <dd>
                        <span class="tabular-nums">{{ $import->created_at?->format('Y-m-d H:i') }}</span>
                        @if($import->user)
                            <span class="text-gray-500"> oleh {{ $import->user->username }}</span>
                        @endif
                    </dd>
                </div>
                <div><dt class="text-gray-500">Format sumber</dt><dd class="text-xs text-gray-600">{{ $import->source_format ?? '—' }}</dd></div>
            </dl>
        </div>
    </div>

    @if($canImport)
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-sm" x-data="{
            paymentAmount: '{{ old('payment_received_amount', $import->payment_received_amount ?? '') }}',
            paymentDate: '{{ old('payment_received_date', $import->payment_received_date?->format('Y-m-d') ?? '') }}',
            cashInId: '{{ old('cash_in_transaction_id', $import->cash_in_transaction_id ?? '') }}',
            suggestions: [],
            suggestionsUrl: @js(route('reports.tax.faktur.cash-in-suggestions')),
            async refreshSuggestions() {
                const params = new URLSearchParams({
                    counterparty_id: @js($import->counterparty_id),
                    reporting_entity_id: @js($import->reporting_entity_id),
                    faktur_number: @js($import->faktur_number),
                    exclude_import_id: @js($import->id),
                });
                if (this.paymentAmount) params.set('payment_received_amount', this.paymentAmount);
                if (this.paymentDate) params.set('payment_received_date', this.paymentDate);
                const response = await fetch(this.suggestionsUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) return;
                const data = await response.json();
                this.suggestions = data.suggestions || [];
            }
        }" x-init="refreshSuggestions()">
            <h3 class="mb-3 font-semibold text-gray-900">Update pembayaran</h3>
            @if(session('error'))
                <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-red-800">{{ session('error') }}</div>
            @endif
            <form method="POST" action="{{ route('reports.tax.faktur.payment.update', $import) }}" class="space-y-3">
                @csrf
                @method('PATCH')
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500" for="show_payment_received_amount">Jumlah diterima — nett + PPN (Rp)</label>
                        <input type="number" step="0.01" id="show_payment_received_amount" name="payment_received_amount"
                               x-model="paymentAmount" @input="refreshSuggestions()"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm tabular-nums">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500" for="show_payment_received_date">Tanggal bayar</label>
                        <input type="date" id="show_payment_received_date" name="payment_received_date"
                               x-model="paymentDate" @change="refreshSuggestions()"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <div x-show="suggestions.length > 0" x-cloak>
                    <label class="mb-1 block text-xs text-gray-500" for="show_cash_in_transaction_id">Link Cash In</label>
                    <select id="show_cash_in_transaction_id" name="cash_in_transaction_id" x-model="cashInId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">— Tidak di-link —</option>
                        <template x-for="item in suggestions" :key="item.id">
                            <option :value="item.id" x-text="`#${item.id} · ${item.date} · ${item.bank_name} · Rp ${formatAmountId(item.total)}${item.invoice ? ' · ' + item.invoice : ''}`"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500" for="show_variance_expense_addrbook_id">Akun biaya selisih</label>
                    <select id="show_variance_expense_addrbook_id" name="variance_expense_addrbook_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">— Tidak ada —</option>
                        @foreach($expenseAccounts as $account)
                            <option value="{{ $account->id }}" @selected((int) old('variance_expense_addrbook_id', $import->variance_expense_addrbook_id) === $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" data-testid="faktur-payment-update">
                    Simpan pembayaran
                </button>
            </form>
        </div>
    @endif

    @if($canImport && $import->canPostConsignmentSell())
        <div class="rounded-xl border border-blue-200 bg-blue-50/40 p-4 shadow-sm text-sm" x-data="{
            lineMode: '{{ old('line_mode', 'summary') }}',
            lineMatches: @js($lineItemMatches).map(function (line) {
                line.selected_item_id = line.best_match ? String(line.best_match.id) : '';
                return line;
            }),
        }">
            <h3 class="mb-1 font-semibold text-gray-900">Post Sell dari faktur</h3>
            <p class="mb-3 text-xs text-gray-600">
                Konsinyasi MDS/Central: Sell = <strong>gross</strong> faktur (DPP+PPN {{ $fmt($gross) }}).
                Cash In = nett+PPN yang diterima ({{ $import->payment_received_amount ? $fmt($import->payment_received_amount) : '—' }}).
                Selisih = margin/biaya → sudah di-book ke akun biaya via Cash Out.
                Default tanggal Sell = tanggal faktur (periode PPN).
            </p>
            <form method="POST" action="{{ route('reports.tax.faktur.post-sell', $import) }}" class="space-y-3">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500" for="post_sell_warehouse_id">Warehouse</label>
                        <select id="post_sell_warehouse_id" name="warehouse_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">— Pilih —</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id', $defaultWarehouseId) === $warehouse->id)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500" for="post_sell_date_source">Tanggal Sell</label>
                        <select id="post_sell_date_source" name="date_source" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="faktur" @selected(old('date_source', 'faktur') === 'faktur')>Tanggal faktur ({{ $import->faktur_date?->format('Y-m-d') }})</option>
                            <option value="cash_in" @selected(old('date_source') === 'cash_in')>Tanggal Cash In / bayar</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500" for="post_sell_invoice_source">Invoice</label>
                        <select id="post_sell_invoice_source" name="invoice_source" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="faktur" @selected(old('invoice_source', 'faktur') === 'faktur')>Nomor faktur ({{ $import->faktur_number }})</option>
                            <option value="cash_in" @selected(old('invoice_source') === 'cash_in')>Invoice Cash In terkait</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500" for="post_sell_line_mode">Baris item</label>
                        <select id="post_sell_line_mode" name="line_mode" x-model="lineMode" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="summary">Satu baris ringkasan (DPP {{ $fmt($import->dpp) }})</option>
                            <option value="mapped">Map per baris faktur</option>
                        </select>
                    </div>
                </div>

                <div x-show="lineMode === 'summary'" x-cloak>
                    <label class="mb-1 block text-xs text-gray-500" for="post_sell_summary_item_id">Item ringkasan</label>
                    <select id="post_sell_summary_item_id" name="summary_item_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">— Pilih item —</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" @selected((int) old('summary_item_id') === $item->id)>
                                {{ $item->name }}@if($item->code) · {{ $item->code }}@endif
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Qty 1 × harga = DPP faktur. Stok harus tersedia di warehouse.</p>
                </div>

                <div x-show="lineMode === 'mapped'" x-cloak class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50 text-left text-xs text-gray-500">
                                <th class="px-3 py-2 font-medium">#</th>
                                <th class="px-3 py-2 font-medium">Nama faktur</th>
                                <th class="px-3 py-2 font-medium">Item inventory</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(line, index) in lineMatches" :key="line.line_no">
                                <tr class="border-b">
                                    <td class="px-3 py-2 tabular-nums" x-text="line.line_no"></td>
                                    <td class="px-3 py-2">
                                        <span x-text="line.name"></span>
                                        <span class="block text-xs text-gray-500" x-text="`Qty ${line.quantity} · Rp ${formatAmountId(line.total)}`"></span>
                                        <template x-if="line.best_match">
                                            <span class="mt-0.5 block text-xs text-green-700" x-text="`Saran: ${line.best_match.name}`"></span>
                                        </template>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="hidden" :name="`mapped_lines[${index}][line_no]`" :value="line.line_no">
                                        <select :name="`mapped_lines[${index}][item_id]`" x-model="line.selected_item_id" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" required>
                                            <option value="">— Pilih —</option>
                                            @foreach($items as $item)
                                                <option value="{{ $item->id }}">{{ $item->name }}@if($item->code) · {{ $item->code }}@endif</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" data-testid="faktur-post-sell">
                    Post Sell dari faktur
                </button>
            </form>
        </div>
    @elseif($canImport && $import->isConsignmentCounterparty() && ! $import->sell_transaction_id)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Link Cash In atau isi jumlah/tanggal pembayaran sebelum posting Sell.
        </div>
    @endif

    @if(count($lineItems) > 0)
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-gray-900">Baris item ({{ count($lineItems) }})</h3>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50 text-left text-xs text-gray-500">
                        <th class="px-3 py-2 font-medium">#</th>
                        <th class="px-3 py-2 font-medium">Nama</th>
                        <th class="px-3 py-2 text-right font-medium">Qty</th>
                        <th class="px-3 py-2 text-right font-medium">Harga</th>
                        <th class="px-3 py-2 text-right font-medium">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lineItems as $item)
                        <tr class="border-b">
                            <td class="px-3 py-2 tabular-nums">{{ $item['line_no'] ?? '' }}</td>
                            <td class="px-3 py-2">{{ $item['name'] ?? '' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ isset($item['quantity']) ? $fmt($item['quantity']) : '' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ isset($item['unit_price']) ? $fmt($item['unit_price']) : '' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ isset($item['total']) ? $fmt($item['total']) : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
