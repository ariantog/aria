@extends('layouts.app')

@section('title', 'Transactions: ' . $item->name)

@section('content')
@php
$base = $isAsset ? '/assetlancar' : '/items';
$breadcrumbs = [
    ['title' => $isAsset ? 'Assets' : 'Items', 'href' => $base],
    ['title' => $item->name, 'href' => $base.'/'.$item->id],
    ['title' => 'Transactions', 'href' => '#'],
];
$typeLabels = [1=>'Buy',2=>'Sell',3=>'Move',15=>'Return',16=>'Production',17=>'Ret. Supplier'];
$typeColors = [
    1=>'bg-emerald-50 text-emerald-600 border-emerald-200',
    2=>'bg-blue-50 text-blue-600 border-blue-200',
    3=>'bg-amber-50 text-amber-600 border-amber-200',
    15=>'bg-purple-50 text-purple-600 border-purple-200',
    16=>'bg-indigo-50 text-indigo-600 border-indigo-200',
    17=>'bg-rose-50 text-rose-600 border-rose-200',
];
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-4">
        <div class="mb-2 flex items-center gap-2">
            <a href="{{ $base }}/{{ $item->id }}" class="text-gray-500 hover:text-gray-900">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="font-mono text-sm text-gray-400">#{{ $item->code }}</span>
        </div>
        <h1 class="mb-1 text-2xl font-bold text-gray-900">Transaction History</h1>
        <p class="text-sm text-gray-500">Full history for <span class="text-blue-600">{{ $item->name }}</span></p>
    </div>

    @include('items.partials.item-tabs', ['active' => 'Transaction'])

    @include('items.partials.item-transaction-filters', [
        'filters' => $filters ?? [],
        'formAction' => $formAction,
        'resetUrl' => $resetUrl,
        'partyLookupUrl' => $partyLookupUrl,
        'selectedParty' => $selectedParty ?? null,
    ])

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
         x-data="itemTransactionsTable()">
        <div class="flex items-center justify-end border-b border-gray-100 px-3 py-2">
            <button type="button"
                    @click="copyRowsTable()"
                    data-testid="copy-item-transactions-table"
                    title="Copy table for Excel"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span x-text="copyFeedback ? 'Copied!' : 'Copy rows'"></span>
            </button>
        </div>
        <div class="overflow-x-auto">
            <table x-ref="itemTransactionsTable" class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                        <th class="px-6 py-3 font-bold" data-copy-col="date">Date</th>
                        <th class="px-6 py-3 font-bold" data-copy-col="type">Type</th>
                        <th class="px-6 py-3 font-bold" data-copy-col="invoice">Invoice</th>
                        <th class="px-6 py-3 text-right font-bold" data-copy-col="price">Price</th>
                        <th class="px-6 py-3 font-bold" data-copy-col="sender">Sender / Source</th>
                        <th class="px-6 py-3 font-bold" data-copy-col="receiver">Receiver / Destination</th>
                        <th class="px-6 py-3 font-bold" data-copy-col="description">Description</th>
                        <th class="px-6 py-3 text-right font-bold" data-copy-col="qty">Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($transactions as $td)
                    @php
                        $tt = (int) $td->transaction_type;
                        $isOut = in_array($tt, [2, 17], true);
                        $signedQty = $isOut ? -abs((float) $td->quantity) : abs((float) $td->quantity);
                        $description = $td->notes ?? optional($td->transaction)->description ?? '-';
                    @endphp
                    <tr class="hover:bg-gray-50/50">
                        <td class="whitespace-nowrap px-6 py-3 font-medium text-gray-700" data-copy-col="date">{{ \Carbon\Carbon::parse($td->date)->format('d M Y') }}</td>
                        <td class="whitespace-nowrap px-6 py-3" data-copy-col="type">
                            <span class="inline-flex rounded border px-2 py-0.5 text-[10px] font-bold uppercase {{ $typeColors[$tt] ?? 'bg-gray-50 text-gray-600 border-gray-200' }}">{{ $typeLabels[$tt] ?? 'Other' }}</span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-3" data-copy-col="invoice">
                            <a href="/transactions/{{ $td->transaction_id }}" class="font-mono text-blue-600 hover:underline">{{ optional($td->transaction)->invoice ?? '-' }}</a>
                        </td>
                        <td class="whitespace-nowrap px-6 py-3 text-right font-medium text-gray-600" data-copy-col="price" data-copy-value="{{ format_copy_number($td->price) }}">{{ format_currency($td->price) }}</td>
                        <td class="px-6 py-3" data-copy-col="sender">
                            <div class="flex flex-col">
                                @if(optional($td->transaction)->sender)
                                    <a href="{{ url('/'.$td->transaction->sender->type_slug.'/'.$td->transaction->sender->id) }}" class="font-medium text-blue-600 hover:underline">{{ $td->transaction->sender->name }}</a>
                                    <span class="text-[10px] font-bold uppercase text-gray-400">ID: {{ $td->transaction->sender->id }}</span>
                                @else
                                    <span class="font-medium text-gray-700">-</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-3" data-copy-col="receiver">
                            <div class="flex flex-col">
                                @if(optional($td->transaction)->receiver)
                                    <a href="{{ url('/'.$td->transaction->receiver->type_slug.'/'.$td->transaction->receiver->id) }}" class="font-medium text-blue-600 hover:underline">{{ $td->transaction->receiver->name }}</a>
                                    <span class="text-[10px] font-bold uppercase text-gray-400">ID: {{ $td->transaction->receiver->id }}</span>
                                @else
                                    <span class="font-medium text-gray-700">-</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-3" data-copy-col="description">
                            <p class="max-w-[200px] truncate text-xs text-gray-500" title="{{ $description !== '-' ? $description : '' }}">{{ $description }}</p>
                        </td>
                        <td class="whitespace-nowrap px-6 py-3 text-right font-mono font-bold {{ $isOut ? 'text-rose-500' : 'text-emerald-500' }}" data-copy-col="qty" data-copy-value="{{ format_copy_number($signedQty) }}">
                            {{ $isOut ? '-' : '+' }}{{ format_amount($td->quantity) }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-6 py-12 text-center italic text-gray-500">{{ !empty($hasActiveFilters) ? 'No transactions match these filters.' : 'No transactions found for this item.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $transactions, 'label' => 'transactions'])
    </div>
</div>
@endsection

@once
    @push('scripts')
    <script>
    function itemTransactionsTable() {
        return {
            copyFeedback: false,
            copyFeedbackTimer: null,
            showCopyFeedback() {
                this.copyFeedback = true;
                clearTimeout(this.copyFeedbackTimer);
                this.copyFeedbackTimer = setTimeout(() => {
                    this.copyFeedback = false;
                }, 2000);
            },
            isCopyColumnVisible(col) {
                return true;
            },
            async copyRowsTable() {
                if (await ariaCopyTable(this.$refs.itemTransactionsTable, (col) => this.isCopyColumnVisible(col))) {
                    this.showCopyFeedback();
                }
            },
        };
    }
    </script>
    @endpush
@endonce
