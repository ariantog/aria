@extends('layouts.app')

@section('title', 'Laporan Laba Rugi')

@section('content')
@php
$fmt = fn ($v) => format_amount($v);
$monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$monthKeys = $report['month_keys'];
$showMonths = count($monthKeys) > 1;
$csvQuery = http_build_query([
    'year' => $filters['year'],
    'month' => $filters['month'],
    'months' => $filters['months'],
    'entity' => $filters['entity'],
    'export' => 'csv',
]);
$xlsxQuery = http_build_query([
    'year' => $filters['year'],
    'month' => $filters['month'],
    'months' => $filters['months'],
    'entity' => $filters['entity'],
    'export' => 'xlsx',
]);
@endphp

<div
    class="flex flex-col gap-4 p-4"
    x-data="{
        pendapatanOpen: false,
        bebanOpen: false,
        togglePendapatan() { this.pendapatanOpen = !this.pendapatanOpen },
        toggleBeban() { this.bebanOpen = !this.bebanOpen },
        isPendapatanOpen() { return this.pendapatanOpen },
        isBebanOpen() { return this.bebanOpen },
    }"
    data-testid="laba-rugi-page"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Laporan Laba Rugi</h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ $report['entity_label'] }} —
                {{ $filters['months'] }} bulan sampai {{ $monthNames[$filters['month']] }} {{ $filters['year'] }}
                ({{ $report['period_start'] }} — {{ $report['period_end'] }})
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('reports.laba-rugi') }}?{{ $csvQuery }}"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                data-testid="laba-rugi-export-csv"
            >
                Export CSV
            </a>
            <a
                href="{{ route('reports.laba-rugi') }}?{{ $xlsxQuery }}"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                data-testid="laba-rugi-export-xlsx"
            >
                Export Excel
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('reports.laba-rugi') }}" class="flex flex-wrap items-end gap-4">
            <div class="grid w-[180px] gap-1.5">
                <label class="text-sm font-medium" for="month">Bulan akhir</label>
                <select id="month" name="month" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((int) $filters['month'] === $m)>{{ $monthNames[$m] }}</option>
                    @endfor
                </select>
            </div>
            <div class="grid w-[140px] gap-1.5">
                <label class="text-sm font-medium" for="year">Tahun</label>
                <select id="year" name="year" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    @foreach($yearList as $y)
                        <option value="{{ $y }}" @selected((int) $filters['year'] === (int) $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid w-[160px] gap-1.5">
                <label class="text-sm font-medium" for="months">Periode</label>
                <select id="months" name="months" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="laba-rugi-months">
                    <option value="1" @selected((int) $filters['months'] === 1)>1 bulan</option>
                    <option value="3" @selected((int) $filters['months'] === 3)>3 bulan</option>
                    <option value="6" @selected((int) $filters['months'] === 6)>6 bulan</option>
                    <option value="12" @selected((int) $filters['months'] === 12)>12 bulan</option>
                </select>
            </div>
            <div class="grid min-w-[220px] gap-1.5">
                <label class="text-sm font-medium" for="entity">Entitas</label>
                <select id="entity" name="entity" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="laba-rugi-entity">
                    <option value="{{ \App\Services\Reporting\LabaRugiService::CONSOLIDATED_ENTITY }}" @selected((int) $filters['entity'] === \App\Services\Reporting\LabaRugiService::CONSOLIDATED_ENTITY)>
                        Konsolidasi (semua entitas aktif)
                    </option>
                    @foreach($entities as $entity)
                        <option value="{{ $entity->id }}" @selected((int) $filters['entity'] === $entity->id)>
                            {{ $entity->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
                <a href="{{ route('reports.laba-rugi') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>
    </div>

    @if($report['internal_lending_total'] >= 0.01)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="laba-rugi-lending-note">
            Internal lending {{ $fmt($report['internal_lending_total']) }} dikeluarkan dari pendapatan dan laba usaha.
        </div>
    @endif

    @if(!$report['is_consolidated'])
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
            HPP dihitung di konsolidasi dari roll-forward persediaan (barang produksi: borongan + Material Produksi ÷ pcs gudang; barang beli: cost item). Per entitas HPP = 0.
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="laba-rugi-statement">
        <table class="w-full min-w-[640px] text-sm">
            <thead>
                <tr class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <th class="py-2 text-left">Akun</th>
                    @if($showMonths)
                        @foreach($monthKeys as $key)
                            <th class="py-2 text-right">{{ $report['month_labels'][$key] }}</th>
                        @endforeach
                    @endif
                    <th class="py-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr>
                    <td class="py-2">
                        <button type="button" class="text-left text-blue-700 hover:underline" @click="togglePendapatan()" data-testid="laba-rugi-pendapatan-toggle">
                            Pendapatan usaha
                        </button>
                    </td>
                    @if($showMonths)
                        @foreach($monthKeys as $key)
                            <td class="py-2 text-right tabular-nums">{{ $fmt($report['pendapatan'][$key]) }}</td>
                        @endforeach
                    @endif
                    <td class="py-2 text-right tabular-nums font-medium" data-testid="laba-rugi-pendapatan">{{ $fmt($report['pendapatan_total']) }}</td>
                </tr>
                <tr x-show="isPendapatanOpen()" x-cloak>
                    <td colspan="{{ $showMonths ? count($monthKeys) + 2 : 2 }}" class="pb-3">
                        <ul class="space-y-1 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                            @forelse($report['drilldown']['pendapatan'] as $row)
                                <li class="flex justify-between gap-3">
                                    <span>
                                        {{ $row['date'] }}
                                        {{ $row['party'] }}
                                        @if($row['invoice'])
                                            <span class="text-gray-400">{{ $row['invoice'] }}</span>
                                        @endif
                                        @if($row['entity_name'])
                                            <span class="text-gray-400">({{ $row['entity_name'] }})</span>
                                        @endif
                                    </span>
                                    <span class="tabular-nums">{{ $fmt($row['amount']) }}</span>
                                </li>
                            @empty
                                <li>Tidak ada penerimaan usaha pada periode ini.</li>
                            @endforelse
                        </ul>
                    </td>
                </tr>
                <tr>
                    <td class="py-2">HPP (roll-forward persediaan)</td>
                    @if($showMonths)
                        @foreach($monthKeys as $key)
                            <td class="py-2 text-right tabular-nums">{{ $fmt($report['hpp'][$key]) }}</td>
                        @endforeach
                    @endif
                    <td class="py-2 text-right tabular-nums" data-testid="laba-rugi-hpp">{{ $fmt($report['hpp_total']) }}</td>
                </tr>
                <tr class="font-medium text-gray-800">
                    <td class="py-2">Laba kotor</td>
                    @if($showMonths)
                        @foreach($monthKeys as $key)
                            <td class="py-2 text-right tabular-nums">{{ $fmt($report['laba_kotor'][$key]) }}</td>
                        @endforeach
                    @endif
                    <td class="py-2 text-right tabular-nums" data-testid="laba-rugi-laba-kotor">{{ $fmt($report['laba_kotor_total']) }}</td>
                </tr>
                <tr class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <td class="pt-4 pb-2" colspan="{{ $showMonths ? count($monthKeys) + 2 : 2 }}">
                        <button type="button" class="text-left text-blue-700 hover:underline normal-case tracking-normal" @click="toggleBeban()" data-testid="laba-rugi-beban-toggle">
                            Beban usaha
                        </button>
                    </td>
                </tr>
                @forelse($report['beban'] as $line)
                    <tr>
                        <td class="py-2 pl-4 text-gray-700">{{ $line['label'] }}</td>
                        @if($showMonths)
                            @foreach($monthKeys as $key)
                                <td class="py-2 text-right tabular-nums">{{ $fmt($line['months'][$key]) }}</td>
                            @endforeach
                        @endif
                        <td class="py-2 text-right tabular-nums">{{ $fmt($line['total']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="py-2 pl-4 text-gray-500" colspan="{{ $showMonths ? count($monthKeys) + 2 : 2 }}">Tidak ada beban usaha.</td>
                    </tr>
                @endforelse
                <tr x-show="isBebanOpen()" x-cloak>
                    <td colspan="{{ $showMonths ? count($monthKeys) + 2 : 2 }}" class="pb-3">
                        <ul class="space-y-1 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                            @forelse($report['drilldown']['beban'] as $row)
                                <li class="flex justify-between gap-3">
                                    <span>
                                        {{ $row['date'] }}
                                        {{ $row['label'] }} — {{ $row['party'] }}
                                        @if($row['entity_name'])
                                            <span class="text-gray-400">({{ $row['entity_name'] }})</span>
                                        @endif
                                    </span>
                                    <span class="tabular-nums">{{ $fmt($row['amount']) }}</span>
                                </li>
                            @empty
                                <li>Tidak ada rincian beban pada periode ini.</li>
                            @endforelse
                        </ul>
                    </td>
                </tr>
                <tr class="font-medium text-gray-700">
                    <td class="py-2">Jumlah beban usaha</td>
                    @if($showMonths)
                        @foreach($monthKeys as $key)
                            <td class="py-2 text-right tabular-nums">{{ $fmt($report['beban_total'][$key]) }}</td>
                        @endforeach
                    @endif
                    <td class="py-2 text-right tabular-nums" data-testid="laba-rugi-beban">{{ $fmt($report['beban_grand_total']) }}</td>
                </tr>
                <tr class="font-medium text-gray-800">
                    <td class="py-2">Laba usaha</td>
                    @if($showMonths)
                        @foreach($monthKeys as $key)
                            <td class="py-2 text-right tabular-nums">{{ $fmt($report['laba_usaha'][$key]) }}</td>
                        @endforeach
                    @endif
                    <td class="py-2 text-right tabular-nums" data-testid="laba-rugi-laba-usaha">{{ $fmt($report['laba_usaha_total']) }}</td>
                </tr>
                <tr>
                    <td class="py-2">Beban pajak (PPh final + tax paid)</td>
                    @if($showMonths)
                        @foreach($monthKeys as $key)
                            <td class="py-2 text-right tabular-nums">{{ $fmt($report['pajak'][$key]) }}</td>
                        @endforeach
                    @endif
                    <td class="py-2 text-right tabular-nums" data-testid="laba-rugi-pajak">{{ $fmt($report['pajak_total']) }}</td>
                </tr>
                <tr class="border-t border-gray-200 font-semibold text-gray-900">
                    <td class="py-3">Laba bersih</td>
                    @if($showMonths)
                        @foreach($monthKeys as $key)
                            <td class="py-3 text-right tabular-nums">{{ $fmt($report['laba_bersih'][$key]) }}</td>
                        @endforeach
                    @endif
                    <td class="py-3 text-right tabular-nums" data-testid="laba-rugi-laba-bersih">{{ $fmt($report['laba_bersih_total']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
