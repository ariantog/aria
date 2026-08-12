@extends('layouts.app')

@section('title', 'Ledger - ' . $account->name)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Journals', 'href' => route('operations.index')],
    ['title' => 'Account List', 'href' => route('account-list.index')],
    ['title' => 'Ledger: ' . $account->name, 'href' => route('account-list.ledger', $account->id)],
];
$fmt = fn($v) => 'Rp ' . number_format((float)($v ?? 0), 0, ',', '.');
@endphp

<div class="flex flex-col gap-4 overflow-x-auto p-4">
    <div class="mb-4 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div class="flex items-center gap-4">
            <a href="{{ route('account-list.index') }}" class="flex h-10 w-10 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-gray-900">Daftar Operasi / Ledger: {{ $account->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">Operation: {{ $account->operation->name ?? 'Uncategorized' }} | Current Balance: <span class="font-semibold text-gray-700">{{ $fmt($account->stat->balance ?? 0) }}</span></p>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('account-list.ledger', $account->id) }}" class="mb-4 flex flex-col items-end gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row">
        <div class="w-full space-y-2 sm:w-auto">
            <label class="text-sm font-medium">From Date</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm">
        </div>
        <div class="w-full space-y-2 sm:w-auto">
            <label class="text-sm font-medium">To Date</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 sm:w-auto">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L14 13.414V19a1 1 0 01-.293.707l-2 2A1 1 0 0110 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
            Filter Ledger
        </button>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50/50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Invoice / Ref</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Debit (In)</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Credit (Out)</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Recorded Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($transactions as $trx)
                    @php
                        $isReceiver = $trx->receiver_id === $account->id;
                        $debit = $isReceiver ? $trx->total : 0;
                        $credit = !$isReceiver ? $trx->total : 0;
                        $recordedBalance = $isReceiver ? $trx->receiver_balance : $trx->sender_balance;
                    @endphp
                    <tr class="hover:bg-gray-50/50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ \Carbon\Carbon::parse($trx->date)->format('d/m/Y') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <div class="font-semibold">{{ $trx->invoice ?: 'N/A' }}</div>
                            <div class="text-xs text-gray-500">{{ $trx->reference_number }}</div>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium text-green-600">{{ $debit > 0 ? $fmt($debit) : '-' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium text-red-600">{{ $credit > 0 ? $fmt($credit) : '-' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ $fmt($recordedBalance) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No transactions found for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $transactions, 'label' => 'transactions'])
    </div>
</div>
@endsection
