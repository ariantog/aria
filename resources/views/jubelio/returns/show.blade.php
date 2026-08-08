@extends('layouts.app')

@section('title', 'Cancellation #' . $return->invoice)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio Cancellations', 'href' => route('jubelio.returns.index')],
    ['title' => 'Invoice ' . $return->invoice, 'href' => route('jubelio.returns.show', $return)],
];
@endphp

<div class="flex flex-col gap-6 p-6">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div class="flex items-center gap-4">
            <a href="{{ route('jubelio.returns.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <h1 class="text-2xl font-bold">Cancellation #{{ $return->invoice }}</h1>
        </div>

        @if($return->status === 0)
        <form method="POST" action="{{ route('jubelio.returns.solve', $return) }}" onsubmit="return confirm('Tandai pembatalan ini selesai tanpa membuat retur?')">
            @csrf
            <button type="submit" class="rounded-lg border border-yellow-400 bg-yellow-50 px-4 py-2 text-sm font-medium text-yellow-800 hover:bg-yellow-100">
                Tandai Selesai
            </button>
        </form>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm lg:col-span-1">
            <h2 class="mb-4 text-lg font-semibold">Cancellation Info</h2>
            <dl class="space-y-3 text-sm">
                <div><dt class="text-gray-400">Invoice</dt><dd class="font-medium">{{ $return->invoice }}</dd></div>
                <div><dt class="text-gray-400">Store</dt><dd class="font-medium">{{ $return->store_name ?: '—' }}</dd></div>
                <div><dt class="text-gray-400">Location</dt><dd class="font-medium">{{ $return->location_name ?: '—' }}</dd></div>
                <div><dt class="text-gray-400">Payment</dt><dd class="font-medium">{{ $return->method_pay ?: '—' }}</dd></div>
                <div><dt class="text-gray-400">Reason</dt><dd class="font-medium">{{ $return->pesan ?: '—' }}</dd></div>
            </dl>
        </div>

        <div class="lg:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-2 text-lg font-semibold">Original Sell Transaction</h2>
                <p class="mb-4 text-sm text-gray-500">
                    {{ $transaction->sender->name ?? '—' }} → {{ $transaction->receiver->name ?? '—' }}
                    · {{ \Carbon\Carbon::parse($transaction->date)->translatedFormat('d/m/Y') }}
                </p>

                @if($return->status === 0)
                <form method="POST" action="{{ route('jubelio.returns.process', $return) }}" class="space-y-4">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-100 text-xs font-semibold uppercase text-gray-500">
                                <tr>
                                    <th class="px-3 py-2"></th>
                                    <th class="px-3 py-2">Item</th>
                                    <th class="px-3 py-2">Code</th>
                                    <th class="px-3 py-2 text-right">Qty</th>
                                    <th class="px-3 py-2 text-right">Price</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($transaction->details as $detail)
                                <tr>
                                    <td class="px-3 py-2">
                                        <input type="checkbox" name="return_item[]" value="{{ $detail->item_id }}" checked
                                               class="rounded border-gray-300 text-blue-600">
                                    </td>
                                    <td class="px-3 py-2">{{ $detail->item->name ?? '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $detail->item->code ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $detail->quantity }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $detail->price, 0, ',', '.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <label for="adjustment" class="mb-1 block text-sm font-medium text-gray-700">Adjustment</label>
                        <input type="number" step="0.01" name="adjustment" id="adjustment" value="0"
                               class="w-48 rounded-md border border-gray-300 px-3 py-2 text-sm">
                    </div>

                    <button type="submit"
                            class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800"
                            data-testid="jubelio-return-process">
                        Buat Transaksi Retur
                    </button>
                </form>
                @else
                <p class="text-sm text-green-700">Pembatalan ini sudah diproses.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
