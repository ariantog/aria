@extends('layouts.app')

@section('title', 'Stats: ' . $item->name)

@section('content')
@php
$base = $isAsset ? '/assetlancar' : '/items';
$breadcrumbs = [
    ['title' => $isAsset ? 'Assets' : 'Items', 'href' => $base],
    ['title' => $item->name, 'href' => $base.'/'.$item->id],
    ['title' => 'Stats', 'href' => '#'],
];

// Pivot data by showdate
$pivot = [];
$order = [];
$totals = [2=>0,3=>0,15=>0,16=>0];
foreach ($data as $row) {
    $d = $row->showdate;
    if (!isset($pivot[$d])) { $pivot[$d] = [2=>0,3=>0,15=>0,16=>0]; $order[] = $d; }
    $t = (int) $row->transaction_type;
    if (isset($pivot[$d][$t])) { $pivot[$d][$t] += (float) $row->total_qty; $totals[$t] += (float) $row->total_qty; }
}
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-4 flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <div class="mb-2 flex items-center gap-2">
                <a href="{{ $base }}/{{ $item->id }}" class="text-gray-500 hover:text-gray-900">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="font-mono text-sm text-gray-400">#{{ $item->code }}</span>
            </div>
            <h1 class="mb-1 text-2xl font-bold text-gray-900">Item Statistics</h1>
            <p class="text-sm text-gray-500">Monthly transaction volume for <span class="text-blue-600">{{ $item->name }}</span></p>
        </div>

        <form method="GET" action="{{ $base }}/{{ $item->id }}/stats" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">Address Book</label>
                <select name="addr" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    <option value="">All Address Books</option>
                    @foreach($addrbooks as $ab)
                    <option value="{{ $ab->id }}" @selected((string)($filters['addr'] ?? '') === (string)$ab->id)>{{ $ab->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Apply Filter</button>
        </form>
    </div>

    @include('items.partials.item-tabs', ['active' => 'Stats'])

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                        <th class="px-6 py-3 font-bold">Month</th>
                        <th class="px-6 py-3 text-center font-bold">Sell</th>
                        <th class="px-6 py-3 text-center font-bold">Move</th>
                        <th class="px-6 py-3 text-center font-bold">Return</th>
                        <th class="px-6 py-3 text-center font-bold">Production</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($order as $d)
                    <tr class="hover:bg-gray-50/50">
                        <td class="whitespace-nowrap px-6 py-3 font-bold text-gray-800">{{ $d }}</td>
                        <td class="px-6 py-3 text-center font-mono text-blue-600">{{ $pivot[$d][2] ? number_format($pivot[$d][2],0,',','.') : '-' }}</td>
                        <td class="px-6 py-3 text-center font-mono text-amber-600">{{ $pivot[$d][3] ? number_format($pivot[$d][3],0,',','.') : '-' }}</td>
                        <td class="px-6 py-3 text-center font-mono text-purple-600">{{ $pivot[$d][15] ? number_format($pivot[$d][15],0,',','.') : '-' }}</td>
                        <td class="px-6 py-3 text-center font-mono text-indigo-600">{{ $pivot[$d][16] ? number_format($pivot[$d][16],0,',','.') : '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center italic text-gray-500">No statistical data available for this period.</td></tr>
                    @endforelse
                </tbody>
                @if(count($order) > 0)
                <tfoot class="border-t border-gray-200 bg-gray-50 font-bold">
                    <tr>
                        <td class="px-6 py-3 text-sm uppercase tracking-wider text-gray-700">Total</td>
                        <td class="px-6 py-3 text-center font-mono text-blue-600">{{ number_format($totals[2],0,',','.') }}</td>
                        <td class="px-6 py-3 text-center font-mono text-amber-600">{{ number_format($totals[3],0,',','.') }}</td>
                        <td class="px-6 py-3 text-center font-mono text-purple-600">{{ number_format($totals[15],0,',','.') }}</td>
                        <td class="px-6 py-3 text-center font-mono text-indigo-600">{{ number_format($totals[16],0,',','.') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
