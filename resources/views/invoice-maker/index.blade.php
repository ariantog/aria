@extends('layouts.app')

@section('title', 'Invoice Maker')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Invoice Maker', 'href' => route('invoice-maker.index')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Invoice Maker</h2>
            <p class="mt-0.5 text-sm text-gray-500">Create invoices without a sell transaction — useful when billing before production.</p>
        </div>
        @if($can['create'])
        <a href="{{ route('invoice-maker.create') }}"
           class="inline-flex items-center gap-1.5 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Invoice
        </a>
        @endif
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('invoice-maker.index') }}" class="flex gap-2">
        <input type="search" name="search" value="{{ $search }}" placeholder="Search number or recipient..."
               class="w-full max-w-md rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Search</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Number</th>
                        <th class="px-4 py-3 font-semibold">Date</th>
                        <th class="px-4 py-3 font-semibold">Recipient</th>
                        <th class="px-4 py-3 font-semibold">Template</th>
                        <th class="px-4 py-3 text-right font-semibold">Total</th>
                        <th class="px-4 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($invoices as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <a href="{{ route('invoice-maker.show', $row) }}" class="text-blue-700 hover:underline">{{ $row->number }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $row->date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ Str::limit($row->recipient, 60) }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ \App\Models\StandaloneInvoice::TEMPLATES[$row->template] ?? $row->template }}</td>
                        <td class="px-4 py-3 text-right font-mono text-gray-900">{{ format_currency($row->subtotal) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('invoice-maker.show', $row) }}" class="text-sm font-medium text-blue-700 hover:underline">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-500">No invoices yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())
        <div class="border-t border-gray-100 px-4 py-3">{{ $invoices->links() }}</div>
        @endif
    </div>
</div>
@endsection
