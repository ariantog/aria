@extends('layouts.app')

@section('title', 'Archive — Transaction #' . $transaction->invoice)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Archive', 'href' => route('archive.index')],
    ['title' => 'Transactions', 'href' => route('archive.transactions.index')],
    ['title' => 'Invoice #' . ($transaction->invoice ?: $transaction->id), 'href' => route('archive.transactions.show', $transaction->id)],
];
$fmt = fn ($n) => format_amount($n);
$fmtDate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '-';
$tb = $typeBadges[$transaction->type] ?? ['Unknown', 'border-gray-200 bg-white text-gray-600'];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex items-start justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight">Archived Transaction</h1>
                <span class="rounded-md bg-slate-700 px-2 py-0.5 text-xs font-semibold uppercase text-white">Read-only</span>
            </div>
            <p class="text-sm text-gray-500">Invoice #{{ $transaction->invoice ?: $transaction->id }}</p>
        </div>
        <a href="{{ route('archive.transactions.index', ['year' => $transaction->date?->year]) }}" class="text-sm font-medium text-blue-600 hover:underline">← Back</a>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl border bg-white p-4 shadow-sm lg:col-span-1">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Grand Total</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ $fmt($transaction->displaySignedGrandTotal()) }}</div>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Date</dt><dd class="font-medium">{{ $fmtDate($transaction->date) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Type</dt><dd><span class="inline-flex rounded border px-2 py-0.5 text-xs font-medium {{ $tb[1] }}">{{ $tb[0] }}</span></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ $config['sender_label'] }}</dt><dd class="font-medium">{{ $transaction->sender?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ $config['receiver_label'] }}</dt><dd class="font-medium">{{ $transaction->receiver?->name ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-xl border bg-white shadow-sm lg:col-span-2">
            <div class="border-b px-4 py-3 font-semibold">Line Items ({{ $transaction->details->count() }})</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs text-gray-500">
                        <tr>
                            <th class="px-3 py-2 font-medium">Item</th>
                            <th class="px-3 py-2 font-medium">SKU</th>
                            <th class="px-3 py-2 text-right font-medium">Qty</th>
                            <th class="px-3 py-2 text-right font-medium">Price</th>
                            <th class="px-3 py-2 text-right font-medium">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($transaction->details as $detail)
                        <tr>
                            <td class="px-3 py-2">
                                @if($detail->item)
                                <a href="{{ route('archive.items.show', $detail->item_id) }}" class="font-medium text-blue-600 hover:underline">{{ $detail->item->name }}</a>
                                @else
                                <span class="text-gray-500">Item {{ $detail->item_id }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $detail->item?->code ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $detail->quantity }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($detail->price) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($detail->total) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">No line items.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($transaction->description)
    <div class="rounded-xl border bg-white p-4 shadow-sm">
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Description</div>
        <p class="mt-2 whitespace-pre-wrap text-sm text-gray-800">{{ $transaction->description }}</p>
    </div>
    @endif
</div>
@endsection
