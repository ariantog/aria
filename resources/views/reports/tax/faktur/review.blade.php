@extends('layouts.app')

@section('title', 'Review Faktur Import')

@section('content')
@php
$fmt = fn ($v) => format_amount($v);
$gross = $parsed->grossIncludingTax();
@endphp

<div class="flex flex-col gap-4 p-4" x-data="{
    direction: '{{ old('direction', $suggestion['direction']) }}',
    counterpartyId: '{{ old('counterparty_id', $counterpartyGuessId ?? '') }}',
    entityId: '{{ old('reporting_entity_id', $suggestion['reporting_entity_id'] ?? '') }}',
    paymentAmount: '{{ old('payment_received_amount', '') }}',
    paymentDate: '{{ old('payment_received_date', '') }}',
    cashInId: '{{ old('cash_in_transaction_id', '') }}',
    suggestions: @js($cashInSuggestions),
    variance: 0,
    suggestionsUrl: @js(route('reports.tax.faktur.cash-in-suggestions')),
    fakturNumber: @js($parsed->fakturNumber),
    postSell: {{ old('post_sell') ? 'true' : 'false' }},
    lineMode: '{{ old('line_mode', 'summary') }}',
    consignmentIds: @js($consignmentIds),
    lineMatches: @js($lineItemMatches ?? []).map(function (line) {
        line.selected_item_id = line.best_match ? String(line.best_match.id) : '';
        return line;
    }),
    isConsignment() {
        return this.direction === 'keluaran' && this.consignmentIds.includes(Number(this.counterpartyId));
    },
    canOfferSell() {
        return this.isConsignment() && (Boolean(this.cashInId) || (Boolean(this.paymentAmount) && Boolean(this.paymentDate)));
    },
    recalcVariance() {
        const paid = parseFloat(this.paymentAmount) || 0;
        this.variance = paid ? (paid - {{ $gross }}) : 0;
    },
    async refreshSuggestions() {
        if (!this.counterpartyId) {
            this.suggestions = [];
            return;
        }
        const params = new URLSearchParams({
            counterparty_id: this.counterpartyId,
            faktur_number: this.fakturNumber,
        });
        if (this.entityId) params.set('reporting_entity_id', this.entityId);
        if (this.paymentAmount) params.set('payment_received_amount', this.paymentAmount);
        if (this.paymentDate) params.set('payment_received_date', this.paymentDate);
        const response = await fetch(this.suggestionsUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
        });
        if (!response.ok) return;
        const data = await response.json();
        this.suggestions = data.suggestions || [];
        if (!this.cashInId && this.suggestions.length > 0) {
            this.cashInId = String(this.suggestions[0].id);
        }
    }
}" x-init="recalcVariance()">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Review Faktur</h2>
        <p class="mt-1 text-sm text-gray-500">Nomor {{ $parsed->fakturNumber }} — map arah pajak dan lawan transaksi.</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-sm">
            <h3 class="mb-3 font-semibold text-gray-900">Dari PDF</h3>
            <dl class="space-y-2">
                <div><dt class="text-gray-500">Penjual (PKP)</dt><dd>{{ $parsed->sellerName }} <span class="text-xs text-gray-400">{{ $parsed->sellerNpwp }}</span></dd></div>
                <div><dt class="text-gray-500">Pembeli</dt><dd>{{ $parsed->buyerName }} <span class="text-xs text-gray-400">{{ $parsed->buyerNpwp }}</span></dd></div>
                <div class="pt-1">
                    @include('reports.tax.faktur.partials.amount-rows', [
                        'fmt' => $fmt,
                        'hargaJual' => $parsed->grossTotal,
                        'potongan' => $parsed->discountTotal,
                        'uangMuka' => $parsed->downPaymentTotal,
                        'dpp' => $parsed->dpp,
                        'ppn' => $parsed->ppn,
                        'ppnbm' => $parsed->ppnbm,
                        'total' => $gross,
                    ])
                </div>
                @if($expectedPaymentDate)
                    <div><dt class="text-gray-500">Perkiraan jatuh tempo</dt><dd>{{ $expectedPaymentDate }} <span class="text-xs text-gray-400">(dari payment due day lawan)</span></dd></div>
                @endif
            </dl>
            @if(count($parsed->lineItems) > 0)
                <p class="mt-3 text-xs text-gray-500">{{ count($parsed->lineItems) }} baris item dibaca dari PDF.</p>
            @endif
        </div>

        <form method="POST" action="{{ route('reports.tax.faktur.store') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm space-y-4">
            @csrf

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Kami sebagai</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="direction" value="keluaran" x-model="direction" class="rounded border-gray-300">
                        Penjual — PPN keluaran (e.g. jual ke MDS)
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="direction" value="masukan" x-model="direction" class="rounded border-gray-300">
                        Pembeli — PPN masukan (e.g. beli internet, bahan)
                    </label>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="reporting_entity_id">Reporting entity (PKP)</label>
                <select id="reporting_entity_id" name="reporting_entity_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" x-model="entityId" @change="refreshSuggestions()">
                    @foreach($entities as $entity)
                        <option value="{{ $entity->id }}" @selected((int) old('reporting_entity_id', $suggestion['reporting_entity_id']) === $entity->id)>
                            {{ $entity->name }}{{ $entity->is_pkp ? '' : ' (non-PKP)' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="counterparty_combobox" x-text="direction === 'keluaran' ? 'Customer / pembeli' : 'Penjual / lawan transaksi'"></label>
                <div class="relative"
                     x-data="asyncCombobox({
                        endpoint: @js(route('reports.tax.faktur.counterparty-lookup')),
                        placeholder: 'Cari customer, reseller, atau ledger…',
                        initial: @js($counterpartyGuess ? ['id' => $counterpartyGuess->id, 'name' => $counterpartyGuess->name.($counterpartyGuess->payment_due_day ? ' (tgl '.$counterpartyGuess->payment_due_day.')' : '')] : null),
                        onSelect: (item) => {
                            counterpartyId = item ? String(item.id) : '';
                            refreshSuggestions();
                        }
                     })"
                     x-init="init()">
                    <input type="hidden" name="counterparty_id" :value="selected ? selected.id : ''" required>
                    <div class="relative flex h-[38px] w-full overflow-hidden rounded-lg border border-gray-300 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
                        <input type="text"
                               id="counterparty_combobox"
                               x-model="query"
                               @input="handleInput()"
                               @focus="handleFocus()"
                               @keydown="handleKeydown($event)"
                               @keyup="handleKeyup($event)"
                               :readonly="keyboardNavLock()"
                               :placeholder="placeholder"
                               class="flex-1 border-none bg-transparent px-3 py-2 text-sm outline-none placeholder-gray-400"
                               autocomplete="off"
                               data-testid="counterparty-select">
                        <button type="button"
                                x-show="selected"
                                @click="clearSelection()"
                                class="flex items-center px-2 text-gray-400 hover:text-gray-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <button type="button"
                                @click="open = !open; if(!items.length) doSearch(query)"
                                class="flex items-center px-2 text-gray-400">
                            <svg x-show="!loading" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                            <svg x-show="loading" class="h-4 w-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        </button>
                    </div>
                    <div x-show="open" x-cloak @click.away="open = false" class="combobox-options">
                        <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400" x-text="emptyMessage()"></div>
                        <template x-for="(item, idx) in items" :key="item.id">
                            <div @click="selectItem(item)"
                                 @mouseenter="activeIndex = idx"
                                 class="combobox-option"
                                 :class="{ 'active': activeIndex === idx }">
                                <span class="block font-medium" x-text="item.name + (item.payment_due_day ? ' (tgl ' + item.payment_due_day + ')' : '')"></span>
                                <span x-show="item.ledger_hint" class="block text-xs text-gray-500 line-clamp-2" x-text="item.ledger_hint"></span>
                            </div>
                        </template>
                    </div>
                </div>
                <p class="mt-1 text-xs text-gray-500">Customer, reseller, atau akun ledger.</p>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <p class="mb-2 text-sm font-medium text-gray-900">Pembayaran (opsional)</p>
                <p class="mb-3 text-xs text-gray-500">Jika sudah dibayar. Selisih vs faktur (biaya MDS, sewa, dll.) bisa dialokasikan ke akun biaya.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500" for="payment_received_amount">Jumlah diterima (Rp)</label>
                        <input type="number" step="0.01" id="payment_received_amount" name="payment_received_amount"
                               x-model="paymentAmount" @input="recalcVariance(); refreshSuggestions()"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm tabular-nums">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500" for="payment_received_date">Tanggal bayar</label>
                        <input type="date" id="payment_received_date" name="payment_received_date" value="{{ old('payment_received_date') }}"
                               x-model="paymentDate" @change="refreshSuggestions()"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-600" x-show="paymentAmount">
                    Selisih vs faktur: <span class="font-medium tabular-nums" x-text="formatAmountId(variance)"></span>
                </p>
                <div class="mt-3" x-show="suggestions.length > 0" x-cloak>
                    <label class="mb-1 block text-xs text-gray-500" for="cash_in_transaction_id">Link Cash In (opsional)</label>
                    <select id="cash_in_transaction_id" name="cash_in_transaction_id" x-model="cashInId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" data-testid="cash-in-select">
                        <option value="">— Tidak di-link —</option>
                        <template x-for="item in suggestions" :key="item.id">
                            <option :value="item.id" x-text="`#${item.id} · ${item.date} · ${item.bank_name} · Rp ${formatAmountId(item.total)}${item.invoice ? ' · ' + item.invoice : ''}`"></option>
                        </template>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Prioritas: customer + bank entitas, lalu jumlah & tanggal.</p>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs text-gray-500" for="variance_expense_addrbook_id">Akun biaya selisih (e.g. Biaya MDS)</label>
                    <select id="variance_expense_addrbook_id" name="variance_expense_addrbook_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">— Tidak ada / nanti —</option>
                        @foreach($expenseAccounts as $account)
                            <option value="{{ $account->id }}" @selected((int) old('variance_expense_addrbook_id') === $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4" x-show="direction === 'keluaran'" x-cloak>
                <p class="mb-1 text-sm font-medium text-gray-900">Link Sell yang sudah ada</p>
                <p class="mb-2 text-xs text-gray-500">Satu faktur bisa ditutup beberapa invoice Sell. Tidak perlu input Sell ulang.</p>
                @if(count($sellSuggestions ?? []) > 0)
                    <div class="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2">
                        @foreach($sellSuggestions as $sell)
                            <label class="flex items-start gap-2 text-sm">
                                <input type="checkbox" name="sell_transaction_ids[]" value="{{ $sell['id'] }}" class="mt-0.5 rounded border-gray-300">
                                <span>
                                    #{{ $sell['id'] }} · {{ $sell['date'] }} · {{ $sell['invoice'] ?: 'tanpa invoice' }}
                                    <span class="block text-xs text-gray-500">{{ $sell['warehouse_name'] }} · DPP {{ $fmt($sell['dpp']) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-gray-500">Tidak ada Sell customer ini di jendela tanggal. Link nanti dari halaman detail.</p>
                @endif
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="notes">Catatan</label>
                <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="border-t border-gray-100 pt-4" x-show="canOfferSell()" x-cloak>
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="post_sell" value="1" x-model="postSell" class="mt-0.5 rounded border-gray-300" data-testid="faktur-post-sell-on-save">
                    <span>
                        <span class="font-medium text-gray-900">Post Sell dari faktur setelah simpan</span>
                        <span class="block text-xs text-gray-500">Konsinyasi MDS/Central: Sell = harga jual − potongan − uang muka + PPN. Entity pajak dari bank Cash In terkait.</span>
                    </span>
                </label>
                <div class="mt-3 space-y-3" x-show="postSell" x-cloak>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs text-gray-500" for="review_warehouse_id">Warehouse</label>
                            <select id="review_warehouse_id" name="warehouse_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" :required="postSell">
                                <option value="">— Pilih —</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id', $defaultWarehouseId ?? null) === $warehouse->id)>{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500" for="review_date_source">Tanggal Sell</label>
                            <select id="review_date_source" name="date_source" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="faktur" @selected(old('date_source', 'faktur') === 'faktur')>Tanggal faktur ({{ $parsed->fakturDate?->format('Y-m-d') }})</option>
                                <option value="cash_in" @selected(old('date_source') === 'cash_in')>Tanggal Cash In / bayar</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500" for="review_invoice_source">Invoice</label>
                            <select id="review_invoice_source" name="invoice_source" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="faktur" @selected(old('invoice_source', 'faktur') === 'faktur')>Nomor faktur ({{ $parsed->fakturNumber }})</option>
                                <option value="cash_in" @selected(old('invoice_source') === 'cash_in')>Invoice Cash In terkait</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500" for="review_line_mode">Baris item</label>
                            <select id="review_line_mode" name="line_mode" x-model="lineMode" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="summary">Satu baris ringkasan (DPP {{ $fmt($parsed->dpp) }})</option>
                                <option value="mapped">Map per baris faktur</option>
                            </select>
                        </div>
                    </div>
                    <div x-show="lineMode === 'summary'" x-cloak>
                        <label class="mb-1 block text-xs text-gray-500" for="review_summary_item_id">Item ringkasan</label>
                        <select id="review_summary_item_id" name="summary_item_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">— Pilih item —</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" @selected((int) old('summary_item_id') === $item->id)>
                                    {{ $item->name }}@if($item->code) · {{ $item->code }}@endif
                                </option>
                            @endforeach
                        </select>
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
                                            <template x-if="line.best_match">
                                                <span class="mt-0.5 block text-xs text-green-700" x-text="`Saran: ${line.best_match.name}`"></span>
                                            </template>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="hidden" :name="`mapped_lines[${index}][line_no]`" :value="line.line_no">
                                            <select :name="`mapped_lines[${index}][item_id]`" x-model="line.selected_item_id" class="w-full rounded border border-gray-300 px-2 py-1 text-sm">
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
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" data-testid="faktur-submit">
                    Simpan ke laporan PPN
                </button>
                <a href="{{ route('reports.tax.faktur.create') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
