@extends('layouts.app')

@section('title', 'Location Customers')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Locations', 'href' => route('locations.index')],
    ['title' => $location->name, 'href' => route('locations.customers', $location)],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $location->name }}</h2>
            <p class="mt-0.5 text-sm text-gray-500">Manage customers linked to this location.</p>
        </div>
        <a href="{{ route('locations.index') }}"
           class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
            Back to Locations
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-900">Add customer</h3>
        <p class="mt-0.5 text-sm text-gray-500">Search by name, phone, or member ID.</p>
        <form method="GET" action="{{ route('locations.customers', $location) }}" class="mt-3 flex flex-wrap items-end gap-2">
            <div class="min-w-[16rem] flex-1">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search customers..."
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Search</button>
        </form>

        @if(($filters['q'] ?? '') !== '' && $candidates->isNotEmpty())
        <div class="mt-4 divide-y divide-gray-100 rounded-lg border border-gray-200">
            @foreach($candidates as $candidate)
            <div class="flex items-center justify-between gap-3 px-4 py-3">
                <div>
                    <div class="font-medium text-gray-900">{{ $candidate->name }}</div>
                    <div class="text-xs text-gray-500">
                        @if($candidate->phone) {{ $candidate->phone }} @endif
                        @if($candidate->memberId) · {{ $candidate->memberId }} @endif
                    </div>
                </div>
                <form method="POST" action="{{ route('locations.customers.attach', $location) }}">
                    @csrf
                    <input type="hidden" name="customer_id" value="{{ $candidate->id }}">
                    <button type="submit" class="rounded-md bg-blue-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Add</button>
                </form>
            </div>
            @endforeach
        </div>
        @elseif(($filters['q'] ?? '') !== '')
        <p class="mt-3 text-sm text-gray-500">No matching customers found, or they are already linked.</p>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h3 class="text-sm font-semibold text-gray-900">Linked customers ({{ $assigned->count() }})</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Member ID</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($assigned as $addrbook)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900">
                            <a href="{{ route('addrbook.type.show', ['type' => 'customer', 'addrbook' => $addrbook->id]) }}" class="text-blue-600 hover:underline">{{ $addrbook->name }}</a>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-gray-500">{{ $addrbook->phone ?: '—' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-gray-500">{{ $addrbook->memberId ?: '—' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right">
                            <form method="POST" action="{{ route('locations.customers.detach', [$location, $addrbook]) }}" onsubmit="return confirm('Remove this customer from {{ $location->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50">Remove</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">No customers linked to this location yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
