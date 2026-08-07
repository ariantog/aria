@extends('layouts.app')

@section('title', 'Jubelio Cancellations')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio Cancellations', 'href' => route('jubelio.returns.index')],
];
$showSolved = ($filters['status'] ?? '') === 'SOLVED';
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <h1 class="flex items-center gap-2 text-2xl font-bold">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            Jubelio Cancellations
        </h1>

        <form method="GET" action="{{ route('jubelio.returns.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
            <input type="text" name="invoice" value="{{ $filters['invoice'] ?? '' }}" placeholder="Invoice..."
                   class="h-9 rounded-md border border-gray-300 px-3 text-sm">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="h-9 rounded-md border border-gray-300 px-3 text-sm">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="h-9 rounded-md border border-gray-300 px-3 text-sm">
            <button type="submit" class="rounded-lg bg-blue-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
        </form>
    </div>

    <div class="flex gap-2">
        <a href="{{ route('jubelio.returns.index') }}"
           class="rounded-lg px-3 py-1.5 text-sm font-medium {{ ! $showSolved ? 'bg-blue-700 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
            Pending
        </a>
        <a href="{{ route('jubelio.returns.index', ['status' => 'SOLVED']) }}"
           class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $showSolved ? 'bg-blue-700 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
            Selesai
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 font-semibold text-gray-600 uppercase">
                <tr>
                    <th class="px-6 py-4">Updated</th>
                    <th class="px-6 py-4">Invoice</th>
                    <th class="px-6 py-4">Store</th>
                    <th class="px-6 py-4">Location</th>
                    <th class="px-6 py-4">Reason</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($returns as $entry)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-xs text-gray-500">{{ \Carbon\Carbon::parse($entry->updated_at)->translatedFormat('d/m/Y H:i') }}</td>
                    <td class="px-6 py-4 font-mono text-xs">{{ $entry->invoice }}</td>
                    <td class="px-6 py-4">{{ $entry->store_name ?: '—' }}</td>
                    <td class="px-6 py-4">{{ $entry->location_name ?: '—' }}</td>
                    <td class="px-6 py-4 max-w-xs truncate text-xs text-gray-600" title="{{ $entry->pesan }}">{{ $entry->pesan ?: '—' }}</td>
                    <td class="px-6 py-4 text-center">
                        @if($entry->status === 0)
                        <span class="inline-flex rounded bg-yellow-50 px-2 py-0.5 text-xs font-medium text-yellow-700">Pending</span>
                        @else
                        <span class="inline-flex rounded bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Selesai</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="{{ route('jubelio.returns.show', $entry) }}"
                           class="text-sm font-medium text-blue-700 hover:underline">Detail</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-400 italic">Tidak ada data pembatalan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $returns->links() }}
</div>
@endsection
