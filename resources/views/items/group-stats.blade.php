@extends('layouts.app')

@section('title', 'Group Stats: ' . $group->name)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Items', 'href' => route('items.index')],
    ['title' => 'Groups', 'href' => route('items.group')],
    ['title' => $group->name, 'href' => route('items.group-detail', $group->id)],
    ['title' => 'Stats', 'href' => '#'],
];

$pivot = [];
$order = [];
$totals = ['sell'=>0,'move'=>0,'return'=>0,'production'=>0];
$map = [2=>'sell',3=>'move',15=>'return',16=>'production'];
foreach ($data as $row) {
    $d = $row->showdate;
    if (!isset($pivot[$d])) { $pivot[$d] = ['sell'=>0,'move'=>0,'return'=>0,'production'=>0]; $order[] = $d; }
    $key = $map[(int) $row->transaction_type] ?? null;
    if ($key) { $pivot[$d][$key] += (float) $row->total_qty; $totals[$key] += (float) $row->total_qty; }
}
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-6 flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
        <div class="flex items-center gap-4">
            <a href="{{ route('items.group-detail', $group->id) }}" class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <div class="mb-1 flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Group: {{ $group->name }}</h1>
                    @if($group->alias)<span class="rounded bg-gray-100 px-2 py-0.5 text-sm italic text-gray-500">{{ $group->alias }}</span>@endif
                </div>
                <p class="flex items-center gap-2 text-gray-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Collective Group Performance
                </p>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('items.group-stats', $group->id) }}" class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-3">
        <div class="space-y-1">
            <label class="text-xs font-semibold uppercase text-gray-500">From</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold uppercase text-gray-500">To</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Filter</button>
            <a href="{{ route('items.group-stats', $group->id) }}" class="flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Clear</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-6 py-3 font-bold">Month</th>
                    <th class="px-6 py-3 text-right font-bold">Sell</th>
                    <th class="px-6 py-3 text-right font-bold">Return</th>
                    <th class="px-6 py-3 text-right font-bold">Move</th>
                    <th class="px-6 py-3 text-right font-bold">Production</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 uppercase">
                @forelse($order as $d)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-3 font-medium text-gray-900">{{ $d }}</td>
                    <td class="px-6 py-3 text-right font-mono text-gray-700">{{ $pivot[$d]['sell'] ? number_format($pivot[$d]['sell'],0,',','.') : '-' }}</td>
                    <td class="px-6 py-3 text-right font-mono text-rose-500">{{ $pivot[$d]['return'] ? number_format($pivot[$d]['return'],0,',','.') : '-' }}</td>
                    <td class="px-6 py-3 text-right font-mono text-amber-500">{{ $pivot[$d]['move'] ? number_format($pivot[$d]['move'],0,',','.') : '-' }}</td>
                    <td class="px-6 py-3 text-right font-mono text-indigo-500">{{ $pivot[$d]['production'] ? number_format($pivot[$d]['production'],0,',','.') : '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="h-48 px-6 text-center text-gray-500">No statistical data found for this period.</td></tr>
                @endforelse
                @if(count($order) > 0)
                <tr class="border-t-2 border-gray-200 bg-gray-50 font-bold">
                    <td class="px-6 py-3 uppercase tracking-wider text-gray-900">Total</td>
                    <td class="px-6 py-3 text-right font-mono text-green-600">{{ number_format($totals['sell'],0,',','.') }}</td>
                    <td class="px-6 py-3 text-right font-mono text-rose-600">{{ number_format($totals['return'],0,',','.') }}</td>
                    <td class="px-6 py-3 text-right font-mono text-amber-600">{{ number_format($totals['move'],0,',','.') }}</td>
                    <td class="px-6 py-3 text-right font-mono text-indigo-600">{{ number_format($totals['production'],0,',','.') }}</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
