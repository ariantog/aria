@extends('layouts.app')

@section('title', 'Export Sell')

@php
    $perPage = $perPage ?? (int) request()->query('per_page', 100);
    $exportQuery = request()->query();
    $selectedType = (string) ($filters['type'] ?? '');
@endphp

@section('content')
<div class="flex flex-col gap-3 p-3 sm:p-4">

    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Export Sell</h2>
            <p class="mt-0.5 text-sm text-gray-500">
                {{ number_format($rows->total()) }} line{{ $rows->total() === 1 ? '' : 's' }} found.
            </p>
        </div>
    </div>

    @include('transactions.partials.export-sell-filters', [
        'formAction' => route('transactions.export-sell'),
        'resetUrl' => route('transactions.export-sell'),
        'filters' => $filters,
        'typeOptions' => $typeOptions,
        'selectedType' => $selectedType,
        'perPage' => $perPage,
        'showPartyFilters' => true,
        'defaultOpen' => true,
        'senderLookupUrl' => $senderLookupUrl,
        'receiverLookupUrl' => $receiverLookupUrl,
        'senderLabel' => $senderLabel,
        'receiverLabel' => $receiverLabel,
        'selectedSender' => $selectedSender,
        'selectedReceiver' => $selectedReceiver,
        'itemLookupUrl' => $itemLookupUrl ?? route('items.index'),
        'selectedItem' => $selectedItem ?? null,
    ])

    <div class="flex items-center justify-end">
        <a href="{{ route('transactions.export-sell.build', $exportQuery) }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Export Excel
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-3 py-3 font-bold">Date</th>
                        <th class="px-3 py-3 font-bold">Type</th>
                        <th class="px-3 py-3 font-bold">Invoice</th>
                        <th class="px-3 py-3 font-bold">Item ID</th>
                        <th class="px-3 py-3 font-bold">Item Code</th>
                        <th class="px-3 py-3 text-right font-bold">Qty</th>
                        <th class="px-3 py-3 text-right font-bold">Discount</th>
                        <th class="px-3 py-3 text-right font-bold">Subtotal</th>
                        <th class="px-3 py-3 font-bold">Sender</th>
                        <th class="px-3 py-3 font-bold">Receiver</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                        @php
                            $itemUrl = \App\Http\Controllers\ExportSellController::itemShowUrl($row->item?->type, (int) $row->item_id);
                            $sender = $row->sender ?? $row->transaction?->sender;
                            $receiver = $row->receiver ?? $row->transaction?->receiver;
                            $senderUrl = \App\Http\Controllers\ExportSellController::addrbookShowUrl($sender);
                            $receiverUrl = \App\Http\Controllers\ExportSellController::addrbookShowUrl($receiver);
                        @endphp
                        <tr class="align-top hover:bg-gray-50">
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">
                                {{ $row->date ? \Illuminate\Support\Carbon::parse($row->date)->format('d M Y') : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <span class="inline-flex rounded border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-bold uppercase text-blue-700">
                                    {{ \App\Models\TransactionDetail::typeLabel((int) $row->transaction_type) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <a href="{{ route('transactions.show', $row->transaction_id) }}" class="font-mono text-blue-600 hover:underline">
                                    {{ $row->transaction?->invoice ?? '—' }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <a href="{{ $itemUrl }}" class="font-mono text-blue-600 hover:underline">{{ $row->item_id }}</a>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                @if($row->item?->code)
                                    <a href="{{ $itemUrl }}" class="font-mono text-blue-600 hover:underline">{{ $row->item->code }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-mono">{{ format_amount($row->quantity) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-mono">{{ format_amount($row->discount) }}%</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-mono">{{ format_currency($row->total) }}</td>
                            <td class="px-3 py-2">
                                @if($senderUrl && $sender)
                                    <a href="{{ $senderUrl }}" class="text-blue-600 hover:underline">{{ $sender->name }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if($receiverUrl && $receiver)
                                    <a href="{{ $receiverUrl }}" class="text-blue-600 hover:underline">{{ $receiver->name }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center text-sm italic text-gray-500">No transaction lines found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $rows, 'label' => 'lines'])
    </div>
</div>
@endsection
