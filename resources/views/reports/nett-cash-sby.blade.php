@extends('layouts.app')

@section('title', 'Nett Cash')

@section('content')
@php
$fmt = fn ($v) => format_currency($v);
$monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$periodLabel = $filters['month']
    ? $monthNames[$filters['month']].' '.$filters['year']
    : 'Tahun '.$filters['year'];
$csvQuery = http_build_query([
    'year' => $filters['year'],
    'month' => $filters['month'] ?? 'all',
    'entity' => $filters['entity'],
    'export' => 'csv',
]);
@endphp

<div class="flex flex-col gap-4 p-4" data-testid="nett-cash-page">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Nett Cash</h2>
            <p class="mt-1 text-sm text-gray-500">
                Kas masuk dari semua customer dan reseller — {{ $report['entity_label'] }}, {{ $periodLabel }}
                ({{ $report['period_start'] }} — {{ $report['period_end'] }}).
                Daftar diisi dari transaksi, bukan ID yang di-hardcode.
            </p>
        </div>
        <a
            href="{{ route('reports.nett-cash-sby') }}?{{ $csvQuery }}"
            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            data-testid="nett-cash-export-csv"
        >
            Export CSV
        </a>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('reports.nett-cash-sby') }}" class="flex flex-wrap items-end gap-4">
            <div class="grid w-[180px] gap-1.5">
                <label class="text-sm font-medium" for="month">Bulan</label>
                <select id="month" name="month" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="nett-cash-month">
                    <option value="all" @selected($filters['month'] === null)>Semua bulan</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((int) ($filters['month'] ?? 0) === $m)>{{ $monthNames[$m] }}</option>
                    @endfor
                </select>
            </div>
            <div class="grid w-[140px] gap-1.5">
                <label class="text-sm font-medium" for="year">Tahun</label>
                <select id="year" name="year" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="nett-cash-year">
                    @foreach($yearList as $y)
                        <option value="{{ $y }}" @selected((int) $filters['year'] === (int) $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid w-[220px] gap-1.5">
                <label class="text-sm font-medium" for="entity">Bank / entitas</label>
                <select id="entity" name="entity" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="nett-cash-entity">
                    <option value="0" @selected((int) $filters['entity'] === 0)>Semua bank</option>
                    @foreach($entities as $entity)
                        <option value="{{ $entity->id }}" @selected((int) $filters['entity'] === (int) $entity->id)>{{ $entity->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800" data-testid="nett-cash-filter">Filter</button>
                <a href="{{ route('reports.nett-cash-sby') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4" data-testid="nett-cash-total">
            <p class="text-sm font-medium text-gray-600">Total cash in (bonus)</p>
            <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $fmt($report['totals']['cash_in']) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $report['totals']['parties'] }} customer / reseller</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4" data-testid="nett-cash-customer-total">
            <p class="text-sm font-medium text-gray-600">Customer</p>
            <p class="mt-2 text-xl font-bold text-gray-900">{{ $fmt($report['totals']['customer_cash_in']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4" data-testid="nett-cash-reseller-total">
            <p class="text-sm font-medium text-gray-600">Reseller</p>
            <p class="mt-2 text-xl font-bold text-gray-900">{{ $fmt($report['totals']['reseller_cash_in']) }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-sm" data-testid="nett-cash-table">
            <thead>
                <tr class="border-b bg-gray-50 text-left">
                    <th class="px-3 py-2 font-medium text-gray-600">Nama</th>
                    <th class="px-3 py-2 font-medium text-gray-600">Jenis</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600">Cash In</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600">Penjualan</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600">Retur</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600"># Cash In</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['rows'] as $row)
                <tr class="border-b {{ $row['deleted'] ? 'opacity-60' : '' }}" data-testid="nett-cash-row-{{ $row['id'] }}">
                    <td class="px-3 py-2 font-medium">
                        <a href="{{ route('addrbook.type.show', ['type' => $row['type_slug'], 'addrbook' => $row['id']]) }}" class="text-blue-600 hover:underline">{{ $row['name'] }}</a>
                        @if($row['deleted'])
                            <span class="ml-2 rounded border border-gray-300 px-1 text-[10px] text-gray-500">Deleted</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-gray-600">{{ $row['type_label'] }}</td>
                    <td class="px-3 py-2 text-right tabular-nums font-semibold text-emerald-700">{{ $fmt($row['cash_in']) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ $fmt($row['sell']) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ $fmt($row['return']) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ $row['txn_count'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="h-24 text-center text-gray-500">Tidak ada cash in customer/reseller di periode ini.</td>
                </tr>
                @endforelse
            </tbody>
            @if($report['rows'] !== [])
            <tfoot>
                <tr class="border-t-2 bg-gray-50 font-bold">
                    <td class="px-3 py-2" colspan="2">Total bonus</td>
                    <td class="px-3 py-2 text-right tabular-nums text-emerald-700" data-testid="nett-cash-footer-total">{{ $fmt($report['totals']['cash_in']) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($report['totals']['sell']) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($report['totals']['return']) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ $report['totals']['parties'] }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    @if($report['lending_rows'] !== [])
    <div class="overflow-hidden rounded-xl border border-amber-200 bg-amber-50/40 shadow-sm" data-testid="nett-cash-lending">
        <div class="px-3 py-2 text-sm font-medium text-amber-900">
            Internal lending — tidak masuk total bonus ({{ $fmt($report['lending_total']) }})
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-y bg-amber-50 text-left">
                    <th class="px-3 py-2 font-medium text-gray-600">Nama</th>
                    <th class="px-3 py-2 font-medium text-gray-600">Jenis</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600">Cash In</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['lending_rows'] as $row)
                <tr class="border-b">
                    <td class="px-3 py-2">
                        <a href="{{ route('addrbook.type.show', ['type' => $row['type_slug'], 'addrbook' => $row['id']]) }}" class="text-blue-600 hover:underline">{{ $row['name'] }}</a>
                    </td>
                    <td class="px-3 py-2 text-gray-600">{{ $row['type_label'] }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['cash_in']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
