@extends('layouts.app')

@section('title', 'Archive')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Archive', 'href' => route('archive.index')],
];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Archive</h1>
        <p class="text-gray-500 dark:text-gray-400">Read-only access to historical transactions and items stored in the separate archive database.</p>
    </div>

    @if($flash['success'] ?? null)
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $flash['success'] }}</div>
    @endif

    @if($flash['error'] ?? null)
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
        <div class="flex flex-wrap items-center gap-3">
            <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $archiveConfigured ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                {{ $archiveConfigured ? 'Connected' : 'Not connected' }}
            </span>
            <span class="text-sm text-gray-600 dark:text-gray-300">Driver: {{ $archiveDriver }}</span>
            <span class="text-sm text-gray-600 dark:text-gray-300">Live retention: {{ $retentionYears }} full year(s) (from {{ $liveStartYear }})</span>
        </div>

        @if(! $archiveConfigured)
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
            Configure <code class="rounded bg-gray-100 px-1">ARCHIVE_DB_*</code> in <code class="rounded bg-gray-100 px-1">.env</code>, then import a mysqldump of production into the archive database.
            On the archive copy, delete transactions for years you still keep on live (≥ {{ $liveStartYear }}).
        </p>
        @elseif($archiveYearRange)
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
            Archive transaction years: <strong>{{ $archiveYearRange[0] }}</strong> – <strong>{{ $archiveYearRange[1] }}</strong>
            ({{ count($archiveYears) }} distinct year(s))
        </p>
        @else
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Archive database is reachable but has no transactions yet.</p>
        @endif
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <a href="{{ route('archive.transactions.index') }}"
           class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800/50">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Archived Transactions</h2>
            <p class="mt-1 text-sm text-gray-500">Browse invoices and line items from the archive database. Read-only.</p>
        </a>

        <a href="{{ route('archive.items.index') }}"
           class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800/50">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Archived Items</h2>
            <p class="mt-1 text-sm text-gray-500">Search obsolete SKUs copied with archived years. Read-only.</p>
        </a>
    </div>
</div>
@endsection
