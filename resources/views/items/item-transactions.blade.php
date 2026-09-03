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

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                        <th class="px-6 py-3 font-bold">Date</th>
                        <th class="px-6 py-3 font-bold">Type</th>
                        <th class="px-6 py-3 font-bold">Invoice</th>
                        <th class="px-6 py-3 text-right font-bold">Price</th>
                        <th class="px-6 py-3 font-bold">Sender / Source</th>
                        <th class="px-6 py-3 font-bold">Receiver / Destination</th>
                        <th class="px-6 py-3 font-bold">Description</th>
                        <th class="px-6 py-3 text-right font-bold">Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($transactions as $td)
                    @php $tt = (int) $td->transaction_type; $isOut = in_array($tt, [2,17], true); @endphp
                    <tr class="hover:bg-gray-50/50">
                        <td class="whitespace-nowrap px-6 py-3 font-medium text-gray-700">{{ \Carbon\Carbon::parse($td->date)->format('d M Y') }}</td>
                        <td class="whitespace-nowrap px-6 py-3">
                            <span class="inline-flex rounded border px-2 py-0.5 text-[10px] font-bold uppercase {{ $typeColors[$tt] ?? 'bg-gray-50 text-gray-600 border-gray-200' }}">{{ $typeLabels[$tt] ?? 'Other' }}</span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-3">
                            <a href="/transactions/{{ $td->transaction_id }}" class="font-mono text-blue-600 hover:underline">{{ optional($td->transaction)->invoice ?? '-' }}</a>
                        </td>
                        <td class="whitespace-nowrap px-6 py-3 text-right font-medium text-gray-600">{{ format_currency($td->price) }}</td>
                        <td class="px-6 py-3">
                            <div class="flex flex-col">
                                <span class="font-medium text-gray-700">{{ optional(optional($td->transaction)->sender)->name ?? '-' }}</span>
                                @if(optional($td->transaction)->sender)<span class="text-[10px] font-bold uppercase text-gray-400">ID: {{ $td->transaction->sender->id }}</span>@endif
                            </div>
                        </td>
                        <td class="px-6 py-3">
                            <div class="flex flex-col">
                                <span class="font-medium text-gray-700">{{ optional(optional($td->transaction)->receiver)->name ?? '-' }}</span>
                                @if(optional($td->transaction)->receiver)<span class="text-[10px] font-bold uppercase text-gray-400">ID: {{ $td->transaction->receiver->id }}</span>@endif
                            </div>
                        </td>
                        <td class="px-6 py-3">
                            <p class="max-w-[200px] truncate text-xs text-gray-500" title="{{ $td->notes ?? optional($td->transaction)->description }}">{{ $td->notes ?? optional($td->transaction)->description ?? '-' }}</p>
                        </td>
                        <td class="whitespace-nowrap px-6 py-3 text-right font-mono font-bold {{ $isOut ? 'text-rose-500' : 'text-emerald-500' }}">
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
