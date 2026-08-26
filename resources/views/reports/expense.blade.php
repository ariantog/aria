@extends('layouts.app')

@section('title', 'Laporan Biaya')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Laporan Biaya', 'href' => route('reports.expense')],
];
$fmt = fn($v) => 'Rp' . format_amount($v, 0);

$totalAccountIn = array_sum($accountReport['cashIn']);
$totalAccountOut = array_sum($accountReport['cashOut']);
$totalAccountNett = $totalAccountIn + $totalAccountOut;
$totalBankIn = array_sum($bankReport['cashIn']);
$totalBankOut = array_sum($bankReport['cashOut']);
$totalBankNett = $totalBankIn + $totalBankOut;
@endphp

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Laporan Biaya Jurnal & Bank</h1>
            <p class="text-zinc-500">
                Data for
                {{ $filters['month'] ? 'Bulan '.$filters['month'].' - '.$filters['year'] : 'Tahun '.$filters['year'] }}
            </p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('reports.expense') }}" class="flex flex-wrap items-end gap-4">
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
                <a href="{{ route('reports.expense') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>
    </div>

    <div class="space-y-12">
        {{-- Biaya Jurnal (Account) --}}
        <div class="space-y-4">
            <h3 class="px-1 text-lg font-bold">Biaya Jurnal (Account)</h3>
            <div class="overflow-hidden rounded-md border bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-zinc-50 text-left">
                            <th class="px-3 py-2 font-medium text-gray-600">Nama Jurnal</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Cash In (Dari Bank)</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Cash Out (Ke Bank)</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Nett</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($accountList as $item)
                        @php $cin = $accountReport['cashIn'][$item->id] ?? 0; $cout = $accountReport['cashOut'][$item->id] ?? 0; $nett = $cin + $cout; @endphp
                        <tr class="border-b">
                            <td class="px-3 py-2 font-medium"><a href="/addrbook/{{ $item->id }}" class="text-blue-600 hover:underline">{{ $item->name }}</a></td>
                            <td class="px-3 py-2 text-right text-emerald-600">{{ $fmt($cin) }}</td>
                            <td class="px-3 py-2 text-right text-rose-600">{{ $fmt($cout) }}</td>
                            <td class="px-3 py-2 text-right font-bold {{ $nett < 0 ? 'text-rose-600' : '' }}">{{ $fmt($nett) }}</td>
                        </tr>
                        @endforeach
                        <tr class="bg-zinc-100/50 font-bold">
                            <td class="px-3 py-2">Total Jurnal</td>
                            <td class="px-3 py-2 text-right text-emerald-600">{{ $fmt($totalAccountIn) }}</td>
                            <td class="px-3 py-2 text-right text-rose-600">{{ $fmt($totalAccountOut) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($totalAccountNett) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Biaya Bank --}}
        <div class="space-y-4">
            <h3 class="px-1 text-lg font-bold">Biaya Bank</h3>
            <div class="overflow-hidden rounded-md border bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-zinc-50 text-left">
                            <th class="px-3 py-2 font-medium text-gray-600">Nama Bank</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Cash In (Dari Jurnal)</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Cash Out (Ke Jurnal)</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Nett</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bankList as $item)
                        @php $cin = $bankReport['cashIn'][$item->id] ?? 0; $cout = $bankReport['cashOut'][$item->id] ?? 0; $nett = $cin + $cout; @endphp
                        <tr class="border-b">
                            <td class="px-3 py-2 font-medium"><a href="/addrbook/{{ $item->id }}" class="text-blue-600 hover:underline">{{ $item->name }}</a></td>
                            <td class="px-3 py-2 text-right text-emerald-600">{{ $fmt($cin) }}</td>
                            <td class="px-3 py-2 text-right text-rose-600">{{ $fmt($cout) }}</td>
                            <td class="px-3 py-2 text-right font-bold {{ $nett < 0 ? 'text-rose-600' : '' }}">{{ $fmt($nett) }}</td>
                        </tr>
                        @endforeach
                        <tr class="bg-zinc-100/50 font-bold">
                            <td class="px-3 py-2">Total Bank</td>
                            <td class="px-3 py-2 text-right text-emerald-600">{{ $fmt($totalBankIn) }}</td>
                            <td class="px-3 py-2 text-right text-rose-600">{{ $fmt($totalBankOut) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($totalBankNett) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-l-4 border-l-emerald-500 bg-white p-4">
            <p class="text-sm text-zinc-500">Total Mutasi Masuk (Cash In)</p>
            <p class="text-right text-2xl font-bold text-emerald-600">{{ $fmt($totalAccountIn) }}</p>
        </div>
        <div class="rounded-xl border border-l-4 border-l-rose-500 bg-white p-4">
            <p class="text-sm text-zinc-500">Total Mutasi Keluar (Cash Out)</p>
            <p class="text-right text-2xl font-bold text-rose-600">{{ $fmt($totalAccountOut) }}</p>
        </div>
        <div class="rounded-xl border border-l-4 border-l-blue-500 bg-white p-4">
            <p class="text-sm text-zinc-500">Total Selisih (Nett)</p>
            <p class="text-right text-2xl font-bold text-blue-600">{{ $fmt($totalAccountNett) }}</p>
        </div>
    </div>
</div>
@endsection
