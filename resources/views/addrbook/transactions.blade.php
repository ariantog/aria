@extends('layouts.app')

@section('title', 'Transactions: ' . $addrbook->name)

@section('content')
@php
$baseUrl = '/' . $addrbook->type_slug . '/' . $addrbook->id . '/transactions';
$breadcrumbs = [
    ['title' => 'Address Book', 'href' => route('addrbook.index')],
    ['title' => $addrbook->name, 'href' => '/' . $addrbook->type_slug . '/' . $addrbook->id],
    ['title' => 'Transactions', 'href' => $baseUrl],
];
$typeNames = collect($transactionTypes)->keyBy('id');
$typeColors = [
    1 => 'text-emerald-700 bg-emerald-50', 2 => 'text-blue-700 bg-blue-50', 3 => 'text-amber-700 bg-amber-50',
    15 => 'text-purple-700 bg-purple-50', 16 => 'text-indigo-700 bg-indigo-50', 17 => 'text-rose-700 bg-rose-50',
];
$idr = fn ($v) => number_format((float) $v, 0, ',', '.');
$balCls = fn ($v) => $v > 0 ? 'text-emerald-600' : ($v < 0 ? 'text-rose-600' : 'text-gray-500');
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    {{-- Header --}}
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <a href="/{{ $addrbook->type_slug }}/{{ $addrbook->id }}" class="text-gray-400 hover:text-gray-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="font-mono text-sm text-gray-400">#{{ $addrbook->id }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Transaction History</h1>
            <p class="text-sm text-gray-500">Full history for <span class="text-blue-600">{{ $addrbook->name }}</span></p>
        </div>
    </div>

    @include('addrbook.partials.tabs', ['active' => 'transactions'])

    {{-- Filters --}}
    <form method="GET" action="{{ $baseUrl }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">From Date</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">To Date</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Type</label>
            <select name="type" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">All Types</option>
                @foreach($transactionTypes as $t)
                    <option value="{{ $t['id'] }}" @selected((string) ($filters['type'] ?? '') === (string) $t['id'])>{{ $t['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Order By</label>
            <select name="order_date" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="date" @selected(($filters['order_date'] ?? 'date') === 'date')>Transaction Date</option>
                <option value="created_at" @selected(($filters['order_date'] ?? '') === 'created_at')>Created At</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Search</button>
            <a href="{{ $baseUrl }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full table-fixed text-left text-xs">
            <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="w-24 px-2 py-2.5 font-bold">Date</th>
                    <th class="w-20 px-2 py-2.5 font-bold">Type</th>
                    <th class="w-28 px-2 py-2.5 font-bold">Invoice</th>
                    <th class="w-12 px-2 py-2.5 text-center font-bold">Items</th>
                    <th class="px-2 py-2.5 font-bold">Sender</th>
                    <th class="w-24 px-2 py-2.5 text-right font-bold">Sender Bal</th>
                    <th class="px-2 py-2.5 font-bold">Receiver</th>
                    <th class="w-24 px-2 py-2.5 text-right font-bold">Recv Bal</th>
                    <th class="w-28 px-2 py-2.5 text-right font-bold">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($transactions as $t)
                    @php
                        $typeVal = $t->type instanceof \App\Enums\TransactionType ? $t->type->value : $t->type;
                        $sBal = (float) $t->sender_balance;
                        if (($can['bank_hidden_balance'] ?? false) && $t->sender?->type_slug === 'bank') $sBal = 0;
                        $rBal = (float) $t->receiver_balance;
                        if (($can['bank_hidden_balance'] ?? false) && $t->receiver?->type_slug === 'bank') $rBal = 0;
                    @endphp
                    <tr class="align-middle hover:bg-gray-50">
                        <td class="px-2 py-2 whitespace-nowrap">
                            <div class="font-medium text-gray-800">{{ \Illuminate\Support\Carbon::parse($t->date)->translatedFormat('d M Y') }}</div>
                            <div class="text-[10px] text-gray-400">{{ $t->created_at?->format('H:i') }}</div>
                        </td>
                        <td class="px-2 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $typeColors[$typeVal] ?? 'text-gray-700 bg-gray-50' }}">
                                {{ $typeNames[$typeVal]['name'] ?? 'Other' }}
                            </span>
                        </td>
                        <td class="truncate px-2 py-2">
                            <a href="/transactions/{{ $t->id }}" class="font-mono text-blue-600 hover:underline" title="{{ $t->invoice_number }}">{{ $t->invoice_number ?: '-' }}</a>
                        </td>
                        <td class="px-2 py-2 text-center font-mono text-gray-500">{{ number_format((float) $t->total_items, 0, ',', '.') }}</td>
                        <td class="truncate px-2 py-2">
                            @if($t->sender)
                                <a href="/{{ $t->sender->type_slug }}/{{ $t->sender->id }}" class="font-medium hover:underline {{ $t->sender->id === $addrbook->id ? 'font-bold text-blue-600' : 'text-gray-600' }}" title="{{ $t->sender->name }}">{{ $t->sender->name }}</a>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-2 py-2 text-right font-mono font-bold {{ $balCls($sBal) }}">{{ $idr($sBal) }}</td>
                        <td class="truncate px-2 py-2">
                            @if($t->receiver)
                                <a href="/{{ $t->receiver->type_slug }}/{{ $t->receiver->id }}" class="font-medium hover:underline {{ $t->receiver->id === $addrbook->id ? 'font-bold text-blue-600' : 'text-gray-600' }}" title="{{ $t->receiver->name }}">{{ $t->receiver->name }}</a>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-2 py-2 text-right font-mono font-bold {{ $balCls($rBal) }}">{{ $idr($rBal) }}</td>
                        <td class="whitespace-nowrap px-2 py-2 text-right font-mono font-bold text-gray-800">IDR {{ $idr($t->grand_total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-12 text-center text-sm italic text-gray-500">No transactions found for this contact.</td></tr>
                @endforelse
            </tbody>
        </table>
        @include('partials.pagination', ['paginator' => $transactions, 'label' => 'transactions'])
    </div>
</div>
@endsection
