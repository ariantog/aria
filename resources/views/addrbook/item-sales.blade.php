@extends('layouts.app')

@section('title', 'Item Sales: ' . $addrbook->name)

@section('content')
@php
$baseUrl = '/' . $addrbook->type_slug . '/' . $addrbook->id . '/item-sales';
$exportUrl = '/' . $addrbook->type_slug . '/' . $addrbook->id . '/item-sales/export';
$perPage = $perPage ?? (int) request()->query('per_page', 100);
$exportQuery = request()->query();
$selectedType = (string) ($filters['type'] ?? '');
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
  <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
    <div>
      <div class="mb-1 flex items-center gap-2">
        <a href="/{{ $addrbook->type_slug }}/{{ $addrbook->id }}" class="text-gray-400 hover:text-gray-700">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <span class="font-mono text-sm text-gray-400">#{{ $addrbook->id }}</span>
      </div>
      <h1 class="text-2xl font-bold text-gray-900">Item Sales</h1>
      <p class="text-sm text-gray-500">
        Item lines for <span class="text-blue-600">{{ $addrbook->name }}</span>
        — {{ number_format($rows->total()) }} line{{ $rows->total() === 1 ? '' : 's' }} found.
      </p>
    </div>
  </div>

  @include('addrbook.partials.tabs', ['active' => 'item-sales'])

  @include('transactions.partials.export-sell-filters', [
      'formAction' => $baseUrl,
      'resetUrl' => $baseUrl,
      'filters' => $filters,
      'typeOptions' => $typeOptions,
      'selectedType' => $selectedType,
      'perPage' => $perPage,
      'showPartyFilters' => false,
      'defaultOpen' => true,
  ])

  @include('transactions.partials.export-sell-table', [
      'rows' => $rows,
      'exportBaseUrl' => $exportUrl,
      'exportQuery' => $exportQuery,
  ])
</div>
@endsection
