@extends('layouts.app')

@section('title', 'Ledger - ' . $account->name)

@section('content')
@php
$ledgerUrl = route('account-list.ledger', $account);
$exportCsvUrl = route('account-list.ledger.export', array_merge(
    ['account_list' => $account->id],
    request()->query(),
));
$breadcrumbs = [
    ['title' => 'Journals', 'href' => route('operations.index')],
    ['title' => 'Account List', 'href' => route('account-list.index')],
    ['title' => 'Ledger: ' . $account->name, 'href' => $ledgerUrl],
];
$balance = $account->stat->balance ?? 0;
@endphp

<div class="flex flex-col gap-3 p-3 sm:p-4">
  <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
    <div>
      <div class="mb-1 flex items-center gap-2">
        <a href="{{ route('account-list.index') }}" class="text-gray-400 hover:text-gray-700">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <span class="font-mono text-sm text-gray-400">#{{ $account->id }}</span>
      </div>
      <h2 class="text-2xl font-bold tracking-tight text-gray-900">Transaction History</h2>
      <p class="mt-0.5 text-sm text-gray-500">
        Full history for <span class="font-medium text-blue-600">{{ $account->name }}</span>
        @if($account->operation)
          <span class="text-gray-400">·</span> {{ $account->operation->name }}
        @endif
      </p>
      <p class="mt-1 text-sm text-gray-500">
        Current balance: <span class="font-semibold tabular-nums text-gray-900">{{ format_amount($balance) }}</span>
      </p>
    </div>
  </div>

  <form method="GET" action="{{ $ledgerUrl }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
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
      <a href="{{ $ledgerUrl }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
    </div>
  </form>

  @include('transactions.partials.list-table', [
    'rows' => $transactions,
    'can' => $can,
    'highlightId' => $account->id,
    'exportCsvUrl' => $exportCsvUrl,
  ])
</div>
@endsection
