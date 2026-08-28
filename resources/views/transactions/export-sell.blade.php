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

    @include('transactions.partials.export-sell-table', [
        'rows' => $rows,
        'exportBaseUrl' => route('transactions.export-sell.build'),
        'exportQuery' => $exportQuery,
    ])
</div>
@endsection
