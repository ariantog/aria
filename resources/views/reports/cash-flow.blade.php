@extends('layouts.app')

@section('title', 'Cash Flow')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Cash Flow', 'href' => route('reports.cash-flow')],
];
$fmt = fn($v) => 'Rp' . number_format((float) $v, 0, ',', '.');
@endphp

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Cash Flow</h1>
            <p class="text-zinc-500">
                Data for
                {{ $filters['month'] ? 'Month '.$filters['month'].' - '.$filters['year'] : 'Year '.$filters['year'] }}
            </p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('reports.cash-flow') }}" class="flex flex-wrap items-end gap-4">
            <div class="grid w-[180px] gap-1.5">
                <label class="text-sm font-medium" for="month">Month</label>
                <select id="month" name="month" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    <option value="0">All Months</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((string)($filters['month'] ?? '0') === (string)$m)>{{ $m }}</option>
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
                <a href="{{ route('reports.cash-flow') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>
    </div>

    <div class="space-y-12">
        @foreach([['By Sender', $groupBySender], ['By Receiver', $groupByReceiver]] as [$title, $data])
        <div class="space-y-4">
            <h3 class="px-1 text-lg font-bold">{{ $title }}</h3>
            <div class="overflow-hidden rounded-md border bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-zinc-50 text-left">
                            <th class="w-[200px] px-3 py-2 font-medium text-gray-600">Type</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Cash In</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Cash Out</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Sell</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Return</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Buy</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Return Supplier</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data as $item)
                        <tr class="border-b transition-colors hover:bg-zinc-50/50">
                            <td class="px-3 py-2 font-bold">{{ $item->type_name }}</td>
                            <td class="px-3 py-2 text-right font-medium text-emerald-600">{{ $fmt($item->cash_in_total) }}</td>
                            <td class="px-3 py-2 text-right font-medium text-rose-600">{{ $fmt($item->cash_out_total) }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ $fmt($item->sell_total) }}</td>
                            <td class="px-3 py-2 text-right font-medium text-rose-500">{{ $fmt($item->return_total) }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ $fmt($item->buy_total) }}</td>
                            <td class="px-3 py-2 text-right font-medium text-emerald-500">{{ $fmt($item->return_suplier) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="h-24 text-center text-gray-500">Data Empty</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
