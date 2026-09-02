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
                    <form method="POST" action="{{ route('reports.tax.faktur.destroy', $import) }}" class="inline"
                          data-testid="faktur-delete-form"
                          onsubmit="return confirm('Hapus faktur {{ $import->faktur_number }} dari laporan PPN? Sell dan Cash In yang sudah di-link tidak ikut terhapus. Nomor ini bisa diimport ulang.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50" data-testid="faktur-delete-btn">
                            Hapus faktur
                        </button>
                    </form>
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
                <div class="pt-1">
                    @include('reports.tax.faktur.partials.amount-rows', [
                        'fmt' => $fmt,
                        'hargaJual' => $import->gross_total,
                        'potongan' => $import->discount_total,
                        'uangMuka' => $import->down_payment_total ?? 0,
                        'dpp' => $import->dpp,
                        'ppn' => $import->ppn,
                        'ppnbm' => $import->ppnbm,
                        'total' => $gross,
                    ])
                </div>
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
                @php $paymentVariance = $import->computedPaymentVariance(); @endphp
                @if($paymentVariance !== null && abs($paymentVariance) > 0.01)
                    <div>
                        <dt class="text-gray-500">Margin / biaya konsinyasi (gross − bayar)</dt>
                        <dd class="tabular-nums">{{ $fmt(abs($paymentVariance)) }}
                            @if($paymentVariance < 0)
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
                        @php
                            $linkedSells = $import->linkedSells();
                            $linkedDpp = $import->linkedSellDpp();
                            $linkedPpn = $import->linkedSellPpn();
                        @endphp
                        @if($linkedSells->isNotEmpty())
                            <ul class="space-y-1">
                                @foreach($linkedSells as $sell)
                                    <li>
                                        <a href="{{ route('transactions.show', $sell) }}" class="text-blue-600 hover:underline">#{{ $sell->id }}</a>
                                        <span class="text-gray-500"> · {{ $sell->date?->format('Y-m-d') }} · {{ $sell->invoice ?: 'tanpa invoice' }} · DPP {{ $fmt(abs((float) $sell->total)) }} + PPN {{ $fmt((float) $sell->ppn) }}</span>
                                        @if($canImport)
                                            <form method="POST" action="{{ route('reports.tax.faktur.unlink-sell', [$import, $sell->id]) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ml-1 text-xs text-red-600 hover:underline" data-testid="faktur-unlink-sell-{{ $sell->id }}">Lepas</button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-1 text-xs text-gray-600">
                                Total Sell: DPP {{ $fmt($linkedDpp) }} + PPN {{ $fmt($linkedPpn) }}
                                @if($import->hasShortLinkedDpp())
                                    <span class="text-amber-800">· sisa vs faktur DPP {{ $fmt($import->remainingSellDpp()) }}</span>
                                @endif
                            </p>
                        @else
                            <span class="text-gray-400">Belum di-link</span>
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

            @if($canCreateCashIn ?? false)
                <form method="POST" action="{{ route('reports.tax.faktur.cash-in.store', $import) }}" class="mb-4 space-y-3 rounded-lg border border-blue-100 bg-blue-50/40 p-3" data-testid="faktur-create-cash-in">
                    @csrf
                    <p class="text-sm font-medium text-gray-900">Buat Cash In dari faktur</p>
                    <p class="text-xs text-gray-500">Otomatis di-link. Sender = lawan transaksi. Invoice = nomor faktur. PPN tidak dicatat (sudah di faktur).</p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs text-gray-500" for="create_cash_in_amount">Jumlah (Rp)</label>
                            <input type="number" step="0.01" min="0.01" id="create_cash_in_amount" name="amount"
                                   value="{{ old('amount', $defaultCashInAmount ?? '') }}"
                                   required
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm tabular-nums"
                                   data-testid="faktur-create-cash-in-amount">
                            @error('amount')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500" for="create_cash_in_date">Tanggal</label>
                            <input type="date" id="create_cash_in_date" name="date"
                                   value="{{ old('date', $defaultCashInDate ?? now()->toDateString()) }}"
                                   min="{{ $cashInMinDate ?? '' }}"
                                   required
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                   data-testid="faktur-create-cash-in-date">
                            @error('date')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500" for="create_cash_in_bank">Bank</label>
                            <select id="create_cash_in_bank" name="account_id" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                    data-testid="faktur-create-cash-in-bank">
                                <option value="">— Pilih —</option>
                                @foreach($cashInBanks ?? [] as $bank)
                                    <option value="{{ $bank->id }}" @selected((int) old('account_id', $defaultCashInBankId ?? null) === (int) $bank->id)>{{ $bank->name }}</option>
                                @endforeach
                            </select>
                            @error('account_id')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500" for="create_cash_in_variance">Akun biaya selisih (opsional)</label>
                        <select id="create_cash_in_variance" name="variance_expense_addrbook_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">— Tidak ada —</option>
                            @foreach($expenseAccounts as $account)
                                <option value="{{ $account->id }}" @selected((int) old('variance_expense_addrbook_id', $import->variance_expense_addrbook_id) === $account->id)>{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" data-testid="faktur-create-cash-in-submit">
                        Buat &amp; link Cash In
                    </button>
                </form>
                <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">Atau link Cash In yang sudah ada</p>
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

    @if($canImport && $import->direction === 'keluaran')
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-sm" x-data="{
            suggestions: [],
            invoiceQuery: '',
            selected: {},
            suggestionsUrl: @js(route('reports.tax.faktur.sell-suggestions')),
            selectedCount() {
                return Object.keys(this.selected).filter((id) => this.selected[id]).length;
            },
            async refreshSuggestions() {
                const params = new URLSearchParams({
                    counterparty_id: @js($import->counterparty_id),
                    faktur_number: @js($import->faktur_number),
                    exclude_import_id: @js($import->id),
                    remaining_dpp: @js(round((float) $import->dpp - $import->linkedSellDpp(), 2)),
                });
                if (@js($import->faktur_date?->format('Y-m-d'))) {
                    params.set('faktur_date', @js($import->faktur_date?->format('Y-m-d')));
                }
                if (this.invoiceQuery) params.set('invoice', this.invoiceQuery);
                const response = await fetch(this.suggestionsUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) return;
                const data = await response.json();
                this.suggestions = data.suggestions || [];
            }
        }" x-init="refreshSuggestions()">
            <h3 class="mb-1 font-semibold text-gray-900">Link Sell yang sudah ada</h3>
            <p class="mb-3 text-xs text-gray-600">
                Barang di faktur MDS/Central sering beda dari Sell gudang. Link satu atau beberapa Sell yang sudah diinput (invoice bisa lebih dari satu).
            </p>
            <div class="mb-3 flex flex-wrap items-end gap-2">
                <div class="min-w-[12rem] flex-1">
                    <label class="mb-1 block text-xs text-gray-500" for="sell_invoice_query">Cari invoice / ID Sell</label>
                    <input type="text" id="sell_invoice_query" x-model="invoiceQuery" @keydown.enter.prevent="refreshSuggestions()"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="INV-… atau 12345">
                </div>
                <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="refreshSuggestions()">Cari</button>
            </div>
            <form method="POST" action="{{ route('reports.tax.faktur.link-sells', $import) }}" class="space-y-3">
                @csrf
                <div class="overflow-hidden rounded-lg border border-gray-200" x-show="suggestions.length > 0" x-cloak>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50 text-left text-xs text-gray-500">
                                <th class="px-3 py-2 font-medium"></th>
                                <th class="px-3 py-2 font-medium">Sell</th>
                                <th class="px-3 py-2 font-medium">Invoice</th>
                                <th class="px-3 py-2 text-right font-medium">DPP</th>
                                <th class="px-3 py-2 text-right font-medium">PPN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="item in suggestions" :key="item.id">
                                <tr class="border-b">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" name="sell_transaction_ids[]" :value="item.id" x-model="selected[item.id]" class="rounded border-gray-300">
                                    </td>
                                    <td class="px-3 py-2 tabular-nums">
                                        #<span x-text="item.id"></span>
                                        <span class="block text-xs text-gray-500" x-text="`${item.date} · ${item.warehouse_name}`"></span>
                                    </td>
                                    <td class="px-3 py-2" x-text="item.invoice || '—'"></td>
                                    <td class="px-3 py-2 text-right tabular-nums" x-text="formatAmountId(item.dpp)"></td>
                                    <td class="px-3 py-2 text-right tabular-nums" x-text="formatAmountId(item.ppn)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500" x-show="suggestions.length === 0" x-cloak>Tidak ada Sell customer ini di jendela tanggal faktur. Coba cari invoice.</p>
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-50"
                        :disabled="selectedCount() === 0" data-testid="faktur-link-sells">
                    Link Sell terpilih
                </button>
            </form>
        </div>
    @endif

    @if($canImport && $import->canPostConsignmentSell())
        <div class="rounded-xl border border-blue-200 bg-blue-50/40 p-4 shadow-sm text-sm" x-data="{
            lineMode: '{{ old('line_mode', 'summary') }}',
            lineMatches: @js(collect($lineItemMatches)->map(function ($line) {
                $oldRows = old('mapped_lines', []);
                $oldRow = collect($oldRows)->firstWhere('line_no', (string) ($line['line_no'] ?? ''))
                    ?? collect($oldRows)->firstWhere('line_no', $line['line_no'] ?? null);
                $itemId = $oldRow['item_id'] ?? ($line['best_match']['id'] ?? '');

                return array_merge($line, ['item_id' => $itemId !== '' && $itemId !== null ? (string) $itemId : '']);
            })->values()->all()),
            allMappedLinesSelected() {
                if (this.lineMode !== 'mapped') {
                    return true;
                }

                return this.lineMatches.every((line) => line.item_id);
            },
        }">
            <h3 class="mb-1 font-semibold text-gray-900">Buat Sell baru (opsional)</h3>
            <p class="mb-3 text-xs text-gray-600">
                Hanya jika Sell untuk faktur ini belum diinput. Biasanya cukup <strong>link Sell yang sudah ada</strong> di atas.
                Sell baru = harga jual − potongan − uang muka + PPN ({{ $fmt($gross) }}).
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

                @if($errors->has('mapped_lines'))
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                        {{ $errors->first('mapped_lines') }}
                    </div>
                @endif

                <div x-show="lineMode === 'summary'" x-cloak>
                    <label class="mb-1 block text-xs text-gray-500" for="post_sell_summary_item_id">Item ringkasan</label>
                    <select id="post_sell_summary_item_id" name="summary_item_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" :disabled="lineMode !== 'summary'">
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
                                        <input type="hidden" :name="`mapped_lines[${index}][line_no]`" :value="line.line_no" :disabled="lineMode !== 'mapped'">
                                        <select
                                            :name="`mapped_lines[${index}][item_id]`"
                                            x-model="line.item_id"
                                            class="w-full rounded border px-2 py-1 text-sm"
                                            :class="lineMode === 'mapped' && !line.item_id ? 'border-amber-400 bg-amber-50' : 'border-gray-300'"
                                            :disabled="lineMode !== 'mapped'"
                                            :required="lineMode === 'mapped'"
                                        >
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

                <button
                    type="submit"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-white"
                    :class="allMappedLinesSelected() ? 'bg-blue-700 hover:bg-blue-800' : 'cursor-not-allowed bg-gray-400'"
                    :disabled="!allMappedLinesSelected()"
                    data-testid="faktur-post-sell"
                >
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
