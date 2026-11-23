@extends('layouts.app')

@section('title', 'Shopee — Sinkron gudang')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Gudang marketplace', 'href' => route('marketplace.warehouse-sync.index', ['channel' => 'shopee'])],
    ['title' => 'Sinkron Shopee', 'href' => route('marketplace.warehouse-sync.create', ['channel' => 'shopee'])],
];
@endphp

<div class="flex flex-col gap-6 p-4" data-testid="marketplace-warehouse-sync-shopee-create">
    <h1 class="text-2xl font-bold">Sinkron gudang Shopee</h1>
    @if(! $connectionReady)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
            Authorize <strong>STOCK CHECKER</strong> di <a href="{{ route('shopee.sync.index') }}" class="underline">halaman Shopee mapping</a>.
        </div>
    @endif
  <form method="POST" action="{{ route('marketplace.warehouse-sync.store', ['channel' => 'shopee']) }}">
        @csrf
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Memuat pickup warehouse dari Shopee Open API untuk shop OAuth yang aktif.</p>
        <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white" @disabled(! $connectionReady)>Sinkron dari Shopee</button>
    </form>
</div>
@endsection