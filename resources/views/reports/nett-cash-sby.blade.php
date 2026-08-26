@extends('layouts.app')

@section('title', 'Nett Cash')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Nett Cash', 'href' => route('reports.nett-cash-sby')],
];
$fmt = fn($v) => 'Rp' . format_amount($v);
$sum = fn($arr) => array_sum((array) $arr);

$totalCashIn = $sum($customerReport->cashIn) + $sum($resellerReport->cashIn);
$totalCashOut = $sum($customerReport->cashOut) + $sum($resellerReport->cashOut);
$totalSell = $sum($customerReport->sell) + $sum($resellerReport->sell);
$totalReturn = $sum($customerReport->return) + $sum($resellerReport->return);
$totalNettSell = $customerReport->nettSell + $resellerReport->nettSell;
@endphp

<div class="flex flex-col gap-6 p-4 pb-20">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Nett Cash</h1>
            <p class="text-zinc-500">
                Data for
                {{ ($filters['month'] && $filters['month'] !== 'all') ? 'Month '.$filters['month'].' - '.$filters['year'] : 'Year '.$filters['year'] }}
            </p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('reports.nett-cash-sby') }}" class="flex flex-wrap items-end gap-4">
            <div class="grid w-[180px] gap-1.5">
                <label class="text-sm font-medium" for="month">Month</label>
                <select id="month" name="month" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    <option value="all">Semua Bulan (Tahunan)</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((string)($filters['month'] ?? 'all') === (string)$m)>{{ $m }}</option>
                    @endfor
                </select>
            </div>
            <div class="grid w-[180px] gap-1.5">
                <label class="text-sm font-medium" for="year">Year</label>
                <select id="year" name="year" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    @foreach($yearList as $y)
                        <option value="{{ $y }}" @selected((int)$filters['year'] === (int)$y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
                <a href="{{ route('reports.nett-cash-sby') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>
    </div>

    <div class="space-y-10">
        @foreach([['Customer', $customerList, $customerReport], ['Reseller', $resellerList, $resellerReport], ['Bank (Account)', $bankList, $bankReport]] as [$title, $list, $report])
        <div class="space-y-4">
            <h3 class="px-1 text-lg font-bold">{{ $title }}</h3>
            <div class="rounded-md border overflow-hidden bg-white">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-zinc-50 text-left">
                            <th class="w-[300px] px-3 py-2 font-medium text-gray-600">{{ $title }}</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Cash In</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Cash Out</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Sell</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Return</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($list as $item)
                        <tr class="border-b {{ $item->deleted_at ? 'opacity-60 grayscale' : '' }}">
                            <td class="px-3 py-2 font-medium">
                                <a href="/{{ $item->type_slug }}/{{ $item->id }}" class="text-blue-600 hover:underline">
                                    {{ $item->name }}
                                    @if($item->deleted_at)
                                        <span class="ml-2 rounded border px-1 py-0 text-[10px]">Deleted</span>
                                    @endif
                                </a>
                            </td>
                            <td class="px-3 py-2 text-right">{{ $fmt($report->cashIn[$item->id] ?? 0) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($report->cashOut[$item->id] ?? 0) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($report->sell[$item->id] ?? 0) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($report->return[$item->id] ?? 0) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="h-24 text-center text-gray-500">Data Empty</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-b bg-zinc-50 font-semibold">
                            <td class="px-3 py-2">Total</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($sum($report->cashIn)) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($sum($report->cashOut)) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($sum($report->sell)) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($sum($report->return)) }}</td>
                        </tr>
                        <tr class="border-t-2 font-bold">
                            <td class="px-3 py-2">Nett</td>
                            <td class="px-3 py-2 text-right text-emerald-600">{{ $fmt($report->nettCash) }}</td>
                            <td></td>
                            <td class="px-3 py-2 text-right text-blue-600">{{ $fmt($report->nettSell) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endforeach

        <div class="space-y-6">
            <h3 class="px-1 text-lg font-bold">Global Summary (Customer Only)</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-sm font-medium text-gray-600">Global Cash In</p>
                    <div class="mt-2 text-xl font-bold">{{ $fmt($totalCashIn) }}</div>
                </div>
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-sm font-medium text-gray-600">Global Cash Out</p>
                    <div class="mt-2 text-xl font-bold">{{ $fmt($totalCashOut) }}</div>
                </div>
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-sm font-medium text-gray-600">Global Sell</p>
                    <div class="mt-2 text-xl font-bold text-blue-600">{{ $fmt($totalSell) }}</div>
                </div>
                <div class="rounded-xl border bg-white p-4">
                    <p class="text-sm font-medium text-gray-600">Global Return</p>
                    <div class="mt-2 text-xl font-bold text-rose-600">{{ $fmt($totalReturn) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4">
                    <p class="text-sm font-medium text-gray-600">Summary Total Cash In</p>
                    <div class="mt-2 text-2xl font-bold text-emerald-700">{{ $fmt($totalCashIn) }}</div>
                </div>
                <div class="rounded-xl border border-blue-200 bg-blue-50/50 p-4">
                    <p class="text-sm font-medium text-gray-600">Global Nett Sell</p>
                    <div class="mt-2 text-2xl font-bold text-blue-700">{{ $fmt($totalNettSell) }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
