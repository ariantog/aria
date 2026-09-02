@extends('layouts.app')

@section('title', 'Deleted Transactions')

@section('content')
@php
    $breadcrumbs = [
        ['title' => 'Transactions', 'href' => route('transactions.index')],
        ['title' => 'Deleted', 'href' => route('transactions.deleted.index')],
    ];

    $fmt = fn ($n) => format_amount($n);
    $fmtDate = function ($d) {
        if (! $d) return '-';
        return \Illuminate\Support\Carbon::parse($d)->format('d/m/y');
    };

    $typeBadges = [
        1  => ['Buy', 'border-emerald-200 bg-emerald-100 text-emerald-700'],
        2  => ['Sell', 'border-blue-200 bg-blue-100 text-blue-700'],
        3  => ['Move', 'border-amber-200 bg-amber-100 text-amber-700'],
        6  => ['Transfer', 'border-cyan-200 bg-cyan-100 text-cyan-700'],
        7  => ['Cash Out', 'border-rose-200 bg-rose-100 text-rose-700'],
        8  => ['Use', 'border-yellow-200 bg-yellow-100 text-yellow-700'],
        9  => ['Cash In', 'border-purple-200 bg-purple-100 text-purple-700'],
        12 => ['Adjust', 'border-indigo-200 bg-indigo-100 text-indigo-700'],
        15 => ['Return', 'border-rose-200 bg-rose-100 text-rose-700'],
        16 => ['Production', 'border-slate-200 bg-slate-100 text-slate-700'],
        17 => ['Ret. Supplier', 'border-orange-200 bg-orange-100 text-orange-700'],
        18 => ['Depreciation', 'border-zinc-200 bg-zinc-100 text-zinc-700'],
    ];
@endphp

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
    <div class="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900">Deleted Transactions</h2>
            <p class="mt-1 text-sm text-zinc-500">Transactions that have been removed from the active list.</p>
        </div>
        <a href="{{ route('transactions.index') }}"
           class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Transactions
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border bg-white text-sm shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-zinc-50/50">
                    <tr class="text-left text-xs text-zinc-500">
                        <th class="whitespace-nowrap px-3 py-3 font-medium">Timeline</th>
                        <th class="px-3 py-3 font-medium">Type</th>
                        <th class="px-3 py-3 font-medium">Invoice</th>
                        <th class="px-3 py-3 font-medium">Description</th>
                        <th class="px-3 py-3 text-right font-medium">Grand Total</th>
                        <th class="px-3 py-3 text-right font-medium">Total Items</th>
                        <th class="px-3 py-3 font-medium">Sender</th>
                        <th class="px-3 py-3 text-right font-medium">Sender Balance</th>
                        <th class="px-3 py-3 font-medium">Receiver</th>
                        <th class="px-3 py-3 text-right font-medium">Receiver Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($transactions as $transaction)
                    @php $tb = $typeBadges[$transaction->type] ?? ['Unknown', 'border-gray-200 bg-white text-gray-600']; @endphp
                    <tr class="transition-colors hover:bg-zinc-50/50">
                        <td class="whitespace-nowrap px-3 py-1 tabular-nums">
                            <a href="{{ route('transactions.deleted.show', $transaction->id) }}" class="flex flex-col gap-0.5 hover:opacity-80">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-8 text-[10px] font-black tracking-tighter text-blue-500 uppercase opacity-70">Trans:</span>
                                    <span class="text-xs font-medium text-zinc-600">{{ $fmtDate($transaction->date) }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-8 text-[10px] font-black tracking-tighter text-red-500 uppercase opacity-70">Del:</span>
                                    <span class="text-xs font-medium text-zinc-600">{{ $fmtDate($transaction->archivedAt()) }}</span>
                                </div>
                            </a>
                        </td>
                        <td class="px-3 py-1">
                            <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-center text-xs {{ $tb[1] }}">{{ $tb[0] }}</span>
                        </td>
                        <td class="px-3 py-1 font-mono text-[10px]">
                            <a href="{{ route('transactions.deleted.show', $transaction->id) }}" class="text-blue-600 hover:underline">{{ $transaction->invoice }}</a>
                        </td>
                        <td class="max-w-[150px] px-3 py-1 text-[10px] text-zinc-500">{{ $transaction->description ?: ($transaction->notes ?: '-') }}</td>
                        <td class="px-3 py-1 text-right font-bold text-zinc-900 tabular-nums">{{ $fmt($transaction->total) }}</td>
                        <td class="px-3 py-1 text-right text-zinc-500 tabular-nums">{{ $fmt($transaction->total_items) }}</td>
                        <td class="max-w-[120px] truncate px-3 py-1 text-zinc-700">{{ $transaction->sender?->name ?: '-' }}</td>
                        <td class="px-3 py-1 text-right italic text-zinc-500 tabular-nums">{{ $fmt($transaction->sender_balance ?? 0) }}</td>
                        <td class="max-w-[120px] truncate px-3 py-1 text-zinc-700">{{ $transaction->receiver?->name ?: '-' }}</td>
                        <td class="px-3 py-1 text-right italic text-zinc-500 tabular-nums">{{ $fmt($transaction->receiver_balance ?? 0) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="h-48 text-center text-gray-500">
                            <div class="flex flex-col items-center gap-2">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-100">
                                    <svg class="h-5 w-5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <p>No deleted transactions found.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t bg-zinc-50/50 p-4">
            {{ $transactions->links() }}
        </div>
    </div>
</div>
@endsection
