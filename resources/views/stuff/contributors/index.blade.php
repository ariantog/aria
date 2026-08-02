@extends('layouts.app')

@section('title', 'Contributors Report')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => route('contributors.index')],
    ['title' => 'Contributors', 'href' => route('contributors.index')],
];
$fmtDate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '';
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Contributors Report</h2>
        <p class="mt-0.5 text-sm text-gray-500">Contributors from {{ $fmtDate($filters['from']) }} &ndash; {{ $fmtDate($filters['to']) }}</p>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('contributors.index') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">From</label>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">To</label>
            <input type="date" name="to" value="{{ $filters['to'] }}" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Addr Book</label>
            <select name="customer_id" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500">
                <option value="">All</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) $filters['customer_id'] === (string) $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Brand</label>
            <select name="filterBrand" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500">
                <option value="">All</option>
                @foreach($brandList as $brand)
                    <option value="{{ $brand['id'] }}" @selected((string) $filters['filterBrand'] === (string) $brand['id'])>{{ $brand['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
            <a href="{{ route('contributors.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
        </div>
    </form>

    {{-- Top 50 Items --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-sm font-bold text-gray-900">Top 50 Items</div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500">Item Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500">Brand</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500">Size</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500">Qty</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500">Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($stats['topItems'] as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 font-medium text-blue-600">{{ $row['item']['name'] ?? '-' }}</td>
                        <td class="px-6 py-3 text-gray-700">{{ $row['brand_label'] ?: '-' }}</td>
                        <td class="px-6 py-3 text-gray-700">{{ $row['type_label'] ?: 'Accessories' }}</td>
                        <td class="px-6 py-3 text-gray-700">{{ $row['size_label'] ?: 'Accessories' }}</td>
                        <td class="px-6 py-3 text-right tabular-nums">{{ number_format($row['qty'], 0, ',', '.') }}</td>
                        <td class="px-6 py-3 text-right tabular-nums font-medium">{{ number_format($row['amount'], 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">No results.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Group by Brand --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-sm font-bold text-gray-900">By Brand</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Brand</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($stats['groupByBrand'] as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $row['brand_label'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['qty'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['amount'], 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No results.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Group by Type --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-sm font-bold text-gray-900">By Type</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Type</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($stats['groupByType'] as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $row['type_label'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['qty'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['amount'], 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No results.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Group by Size --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50 px-5 py-3 text-sm font-bold text-gray-900">By Size</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Size</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($stats['groupBySize'] as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $row['size'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['qty'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['amount'], 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No results.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
