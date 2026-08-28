@extends('layouts.app')

@section('title', 'Archive — Transactions')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Archive', 'href' => route('archive.index')],
    ['title' => 'Transactions', 'href' => route('archive.transactions.index')],
];
$fmt = fn ($n) => format_amount($n);
$fmtDate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/y') : '-';
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Archived Transactions</h1>
            <p class="text-sm text-gray-500">Read-only historical data from the archive database.</p>
        </div>
        <a href="{{ route('archive.index') }}" class="text-sm font-medium text-blue-600 hover:underline">← Back to Archive</a>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-500">Year</label>
            <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" placeholder="All"
                   class="h-9 w-28 rounded-md border border-gray-300 px-3 text-sm">
        </div>
        <div class="min-w-[12rem] flex-1">
            <label class="mb-1 block text-xs font-medium text-gray-500">Search</label>
            <input type="search" name="search" value="{{ $search }}" placeholder="Invoice, description, ID"
                   class="h-9 w-full rounded-md border border-gray-300 px-3 text-sm">
        </div>
        <button type="submit" class="h-9 rounded-md bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border bg-white text-sm shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs text-gray-500">
                        <th class="px-3 py-3 font-medium">Date</th>
                        <th class="px-3 py-3 font-medium">Type</th>
                        <th class="px-3 py-3 font-medium">Invoice</th>
                        <th class="px-3 py-3 font-medium">Description</th>
                        <th class="px-3 py-3 text-right font-medium">Total</th>
                        <th class="px-3 py-3 font-medium">Sender</th>
                        <th class="px-3 py-3 font-medium">Receiver</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($transactions as $transaction)
                    @php $tb = $typeBadges[$transaction->type] ?? ['Unknown', 'border-gray-200 bg-white text-gray-600']; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-3 py-2 tabular-nums">
                            <a href="{{ route('archive.transactions.show', $transaction->id) }}" class="font-medium text-blue-600 hover:underline">{{ $fmtDate($transaction->date) }}</a>
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-flex rounded border px-2 py-0.5 text-xs font-medium {{ $tb[1] }}">{{ $tb[0] }}</span>
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $transaction->invoice ?: '—' }}</td>
                        <td class="max-w-xs truncate px-3 py-2" title="{{ $transaction->description }}">{{ $transaction->description }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($transaction->total) }}</td>
                        <td class="px-3 py-2">{{ $transaction->sender?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $transaction->receiver?->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-gray-500">No archived transactions found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transactions->hasPages())
        <div class="border-t px-3 py-2">{{ $transactions->links() }}</div>
        @endif
    </div>
</div>
@endsection
