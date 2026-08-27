@extends('layouts.app')

@section('title', 'Laporan Pembelian')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Pembelian', 'href' => route('reports.purchase')],
];
$fmt = fn($v) => 'Rp' . format_amount($v, 0);

$totalBuy = array_sum($supplierReport['buy']);
$totalReturn = array_sum($supplierReport['returnSupplier']);
$totalCashInSupplier = array_sum($supplierReport['cashInSupplier']);
$totalCashOutSupplier = array_sum($supplierReport['cashOutSupplier']);
$totalCashInAccount = array_sum($supplierReport['cashInAccount']);
$totalCashOutAccount = array_sum($supplierReport['cashOutAccount']);
$totalCashIn = $totalCashInSupplier + $totalCashInAccount;
$totalCashOut = $totalCashOutSupplier + $totalCashOutAccount;
$nettSupplierBuy = $totalBuy - $totalReturn;
$nettCash = $totalCashOut - $totalCashIn;
@endphp

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Laporan Pembelian</h1>
            <p class="text-zinc-500">
                Data for
                {{ $filters['month'] ? 'Bulan '.$filters['month'].' - '.$filters['year'] : 'Tahun '.$filters['year'] }}
            </p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('reports.purchase') }}" class="flex flex-wrap items-end gap-4">
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
                <a href="{{ route('reports.purchase') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>
    </div>

    <div class="space-y-8">
        {{-- Supplier Buy --}}
        <div class="space-y-4">
            <h3 class="px-1 text-lg font-bold">Supplier Buy</h3>
            <div class="overflow-hidden rounded-md border bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-zinc-50 text-left">
                            <th class="px-3 py-2 font-medium text-gray-600">Supplier</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Buy</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Return</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Nett</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($supplierList as $item)
                        @php $buy = $supplierReport['buy'][$item->id] ?? 0; $ret = $supplierReport['returnSupplier'][$item->id] ?? 0; @endphp
                        <tr class="border-b">
                            <td class="px-3 py-2 font-medium">{{ $item->name }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($buy) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($ret) }}</td>
                            <td class="px-3 py-2 text-right font-bold">{{ $fmt($buy - $ret) }}</td>
                        </tr>
                        @endforeach
                        <tr class="bg-zinc-100/50 font-bold">
                            <td class="px-3 py-2">Total</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($totalBuy) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($totalReturn) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($nettSupplierBuy) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Supplier Cash --}}
        <div class="space-y-4">
            <h3 class="px-1 text-lg font-bold">Supplier Cash</h3>
            <div class="overflow-hidden rounded-md border bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-zinc-50 text-left">
                            <th class="px-3 py-2 font-medium text-gray-600">Supplier</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Cash In</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Cash Out</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Nett</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($supplierList as $item)
                        @php $cin = $supplierReport['cashInSupplier'][$item->id] ?? 0; $cout = $supplierReport['cashOutSupplier'][$item->id] ?? 0; @endphp
                        <tr class="border-b">
                            <td class="px-3 py-2 font-medium">{{ $item->name }}</td>
                            <td class="px-3 py-2 text-right text-emerald-600">{{ $fmt($cin) }}</td>
                            <td class="px-3 py-2 text-right text-rose-600">{{ $fmt($cout) }}</td>
                            <td class="px-3 py-2 text-right font-bold">{{ $fmt($cout - $cin) }}</td>
                        </tr>
                        @endforeach
                        <tr class="bg-zinc-100/50 font-bold">
                            <td class="px-3 py-2">Total</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($totalCashInSupplier) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($totalCashOutSupplier) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($totalCashOutSupplier - $totalCashInSupplier) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Journal Cash --}}
        <div class="space-y-4">
            <h3 class="px-1 text-lg font-bold">Journal Cash (Account)</h3>
            <div class="overflow-hidden rounded-md border bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-zinc-50 text-left">
                            <th class="px-3 py-2 font-medium text-gray-600">Account</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Cash In</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Cash Out</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Nett</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($accountList as $item)
                        @php $cin = $supplierReport['cashInAccount'][$item->id] ?? 0; $cout = $supplierReport['cashOutAccount'][$item->id] ?? 0; @endphp
                        <tr class="border-b">
                            <td class="px-3 py-2 font-medium">{{ $item->name }}</td>
                            <td class="px-3 py-2 text-right text-emerald-600">{{ $fmt($cin) }}</td>
                            <td class="px-3 py-2 text-right text-rose-600">{{ $fmt($cout) }}</td>
                            <td class="px-3 py-2 text-right font-bold">{{ $fmt($cout - $cin) }}</td>
                        </tr>
                        @endforeach
                        <tr class="bg-zinc-100/50 font-bold">
                            <td class="px-3 py-2">Total</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($totalCashInAccount) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($totalCashOutAccount) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($totalCashOutAccount - $totalCashInAccount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-xl border bg-white p-4">
            <p class="text-sm text-zinc-500">Total Buy</p>
            <p class="text-right text-xl font-bold">{{ $fmt($totalBuy) }}</p>
        </div>
        <div class="rounded-xl border bg-white p-4">
            <p class="text-sm text-zinc-500">Total Return</p>
            <p class="text-right text-xl font-bold">{{ $fmt($totalReturn) }}</p>
        </div>
        <div class="rounded-xl border bg-white p-4">
            <p class="text-sm text-zinc-500">Total Cash In</p>
            <p class="text-right text-xl font-bold text-emerald-600">{{ $fmt($totalCashIn) }}</p>
        </div>
        <div class="rounded-xl border bg-white p-4">
            <p class="text-sm text-zinc-500">Total Cash Out</p>
            <p class="text-right text-xl font-bold text-rose-600">{{ $fmt($totalCashOut) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6">
            <p class="text-sm font-medium text-amber-800">Nett Supplier Buy</p>
            <p class="text-right text-3xl font-bold {{ $nettSupplierBuy >= 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $fmt($nettSupplierBuy) }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6">
            <p class="text-sm font-medium text-emerald-800">Nett Cash</p>
            <p class="text-right text-3xl font-bold {{ $nettCash >= 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $fmt($nettCash) }}</p>
        </div>
    </div>
</div>
@endsection
