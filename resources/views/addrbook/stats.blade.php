@extends('layouts.app')

@section('title', 'Stats: ' . $addrbook->name)

@section('content')
@php
$baseUrl = '/' . $addrbook->type_slug . '/' . $addrbook->id . '/stats';
$breadcrumbs = [
    ['title' => 'Address Book', 'href' => route('addrbook.index')],
    ['title' => $addrbook->name, 'href' => '/' . $addrbook->type_slug . '/' . $addrbook->id],
    ['title' => 'Stats', 'href' => $baseUrl],
];
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$categories = [
    'customer' => 'Customer',
    'reseller' => 'Reseller',
    'journal' => 'Journal',
    'bank' => 'Bank',
    'warehouse' => 'Warehouse',
    'other' => 'Lainnya',
];
$hideBank = ($can['bank_hidden_balance'] ?? false);
$isBank = $addrbook->type_slug === 'bank';
$fmt = function ($value, $catKey = null) use ($hideBank, $isBank) {
    if ($hideBank && ($isBank || $catKey === 'bank')) return '0';
    if ((float) $value == 0.0) return '-';
    return format_amount($value);
};
$fmtTotal = function ($value) use ($hideBank, $isBank, $fmt) {
    if ($hideBank && $isBank) return '0';
    return $fmt($value);
};
$curMonth = $filters['month'] ?? 0;
$curYear = $filters['year'] ?? date('Y');
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    {{-- Header --}}
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <a href="/{{ $addrbook->type_slug }}/{{ $addrbook->id }}" class="text-gray-400 hover:text-gray-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="font-mono text-sm text-gray-400">#{{ $addrbook->id }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Activity Summary</h1>
            <p class="text-sm text-gray-500">Categorized transaction statistics for <span class="text-blue-600">{{ $addrbook->name }}</span></p>
        </div>
    </div>

    @include('addrbook.partials.tabs', ['active' => 'stats'])

    {{-- Filters --}}
    <form method="GET" action="{{ $baseUrl }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Month</label>
            <select name="month" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">Full Year (All Months)</option>
                @foreach($months as $i => $m)
                    <option value="{{ $i + 1 }}" @selected((int) $curMonth === $i + 1)>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Year</label>
            <select name="year" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                @foreach($years as $y)
                    <option value="{{ $y }}" @selected((int) $curYear === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Apply Filter</button>
            <a href="{{ $baseUrl }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
        </div>
    </form>

    {{-- Stats Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 bg-gray-50 p-4">
            <h3 class="text-sm font-bold uppercase tracking-widest text-gray-500">
                Summary for {{ $curMonth ? ($months[$curMonth - 1] ?? '') : 'All Year' }} {{ $curYear }}
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                    <tr>
                        <th class="px-6 py-4 font-bold">Type</th>
                        <th class="px-6 py-4 text-right font-bold">Cash In</th>
                        <th class="px-6 py-4 text-right font-bold">Cash Out</th>
                        <th class="px-6 py-4 text-right font-bold">Sell</th>
                        <th class="px-6 py-4 text-right font-bold">Return</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($categories as $key => $label)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><span class="text-sm font-medium text-gray-700">{{ $label }}</span></td>
                            <td class="px-6 py-4 text-right"><span class="font-mono text-sm text-blue-600">{{ $fmt($dataStat['cash_in'][$key] ?? 0, $key) }}</span></td>
                            <td class="px-6 py-4 text-right"><span class="font-mono text-sm text-orange-600">{{ $fmt($dataStat['cash_out'][$key] ?? 0, $key) }}</span></td>
                            <td class="px-6 py-4 text-right"><span class="font-mono text-sm text-emerald-600">{{ $fmt($dataStat['sell'][$key] ?? 0, $key) }}</span></td>
                            <td class="px-6 py-4 text-right"><span class="font-mono text-sm text-purple-600">{{ $fmt($dataStat['return'][$key] ?? 0, $key) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-gray-200 bg-gray-50 font-bold">
                    <tr>
                        <td class="px-6 py-4 text-[10px] uppercase tracking-widest text-gray-500">Total</td>
                        <td class="px-6 py-4 text-right font-mono text-sm text-blue-600">{{ $fmtTotal($dataStat['cash_in']['total'] ?? 0) }}</td>
                        <td class="px-6 py-4 text-right font-mono text-sm text-orange-600">{{ $fmtTotal($dataStat['cash_out']['total'] ?? 0) }}</td>
                        <td class="px-6 py-4 text-right font-mono text-sm text-emerald-600">{{ $fmtTotal($dataStat['sell']['total'] ?? 0) }}</td>
                        <td class="px-6 py-4 text-right font-mono text-sm text-purple-600">{{ $fmtTotal($dataStat['return']['total'] ?? 0) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
