@extends('layouts.app')

@section('title', 'Additional Fees')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Additional Fees', 'href' => route('additional-fees.index')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Additional Fees</h2>
            <p class="mt-0.5 text-sm text-gray-500">Manage additional fees applied to transactions.</p>
        </div>
        @if($can['create'])
        <a href="{{ route('additional-fees.create') }}"
           class="inline-flex items-center gap-1.5 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Fee
        </a>
        @endif
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('additional-fees.index') }}" class="flex items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="max-w-sm flex-1">
            <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Search</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by name…"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
        </div>
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
        <a href="{{ route('additional-fees.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Type</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Value</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($additional_fees as $fee)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-6 py-4 font-semibold text-gray-900">{{ $fee->name }}</td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase {{ $fee->type === 'percent' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $fee->type }}</span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right tabular-nums text-gray-700">
                            {{ $fee->type === 'percent' ? rtrim(rtrim(number_format($fee->value, 2, '.', ''), '0'), '.') . '%' : number_format($fee->value, 0, ',', '.') }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right">
                            <div class="flex justify-end gap-1">
                                @if($can['edit'])
                                <a href="{{ route('additional-fees.edit', $fee->id) }}"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-900" title="Edit">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @endif
                                @if($can['delete'])
                                <form method="POST" action="{{ route('additional-fees.destroy', $fee->id) }}" onsubmit="return confirm('Delete this fee?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">No additional fees found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $additional_fees, 'label' => 'fees'])
    </div>
</div>
@endsection
