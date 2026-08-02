@extends('layouts.app')

@section('title', 'Item Gudang')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Reports', 'href' => '#'],
    ['title' => 'Item Gudang', 'href' => route('reports.warehouse-item')],
];
$fmtNum = fn($v) => number_format((float) $v, 0, ',', '.');
$fmtCur = fn($v) => 'Rp' . number_format((float) $v, 0, ',', '.');
$grandTotalItem = collect($data)->sum(fn($r) => (float) $r->total_item);
$grandTotalQty = collect($data)->sum(fn($r) => (float) $r->total_qty);
$grandTotalCost = collect($data)->sum(fn($r) => (float) $r->total_cost);
@endphp

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Item Gudang</h1>
        <p class="text-zinc-500">Total {{ $totalWarehouse }} Gudang</p>
    </div>

    <div class="space-y-4">
        <h3 class="px-1 text-lg font-bold text-zinc-900">Warehouse Stock</h3>
        <div class="overflow-hidden rounded-md border border-zinc-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-zinc-50 text-left">
                        <th class="px-3 py-2 font-medium text-zinc-900">Gudang</th>
                        <th class="px-3 py-2 text-right font-medium text-zinc-900">Item</th>
                        <th class="px-3 py-2 text-right font-medium text-zinc-900">Qty</th>
                        <th class="px-3 py-2 text-right font-medium text-zinc-900">Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $row)
                    <tr class="border-b transition-colors hover:bg-zinc-50">
                        <td class="px-3 py-2 font-medium">
                            <a href="/warehouse/{{ $row->id }}" class="text-blue-600 hover:underline">{{ $row->nama_gudang }}</a>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmtNum($row->total_item) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmtNum($row->total_qty) }}</td>
                        <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $fmtCur($row->total_cost) }}</td>
                    </tr>
                    @endforeach
                    <tr class="bg-zinc-100/50 font-bold">
                        <td class="px-3 py-2">TOTAL</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmtNum($grandTotalItem) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmtNum($grandTotalQty) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmtCur($grandTotalCost) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-4">
            <p class="mb-1 text-sm text-zinc-500">Total Item (SKU)</p>
            <p class="text-right text-2xl font-bold tabular-nums">{{ $fmtNum($grandTotalItem) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4">
            <p class="mb-1 text-sm text-zinc-500">Total Qty</p>
            <p class="text-right text-2xl font-bold tabular-nums">{{ $fmtNum($grandTotalQty) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4">
            <p class="mb-1 text-sm text-zinc-500">Total Asset (Cost)</p>
            <p class="text-right text-2xl font-bold text-emerald-600 tabular-nums">{{ $fmtCur($grandTotalCost) }}</p>
        </div>
    </div>
</div>
@endsection
