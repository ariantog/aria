@extends('layouts.app')

@section('title', 'Neraca')

@section('content')
@php
$fmt = fn ($v) => format_amount($v);
$monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$persediaan = $report['persediaan'];
$refreshQuery = http_build_query([
    'year' => $filters['year'],
    'month' => $filters['month'],
    'entity' => $filters['entity'],
    'refresh' => 1,
]);
@endphp

<div
    class="flex flex-col gap-4 p-4"
    x-data="{
        kasOpen: false,
        piutangOpen: false,
        hutangOpen: false,
        toggleKas() { this.kasOpen = !this.kasOpen },
        togglePiutang() { this.piutangOpen = !this.piutangOpen },
        toggleHutang() { this.hutangOpen = !this.hutangOpen },
    }"
    data-testid="neraca-page"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Neraca</h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ $report['entity_label'] }} — posisi {{ $monthNames[$filters['month']] }} {{ $filters['year'] }}
                (as of {{ $report['as_of'] }}, {{ $report['source'] === 'snapshot' ? 'snapshot' : 'replay as-of' }})
            </p>
        </div>
        <a
            href="{{ route('reports.neraca') }}?{{ $refreshQuery }}"
            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            data-testid="neraca-refresh"
        >
            Recalculate as-of
        </a>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('reports.neraca') }}" class="flex flex-wrap items-end gap-4">
            <div class="grid w-[180px] gap-1.5">
                <label class="text-sm font-medium" for="month">Bulan</label>
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
            <div class="grid min-w-[220px] gap-1.5">
                <label class="text-sm font-medium" for="entity">Entitas</label>
                <select id="entity" name="entity" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="neraca-entity">
                    <option value="{{ \App\Services\Reporting\NeracaService::CONSOLIDATED_ENTITY }}" @selected((int) $filters['entity'] === \App\Services\Reporting\NeracaService::CONSOLIDATED_ENTITY)>
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
                <a href="{{ route('reports.neraca') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="neraca-persediaan">
        <h3 class="mb-1 text-sm font-semibold text-gray-900">Persediaan (grup, roll-forward dari Januari 2026)</h3>
        <p class="mb-3 text-xs text-gray-500">
            @if(!empty($persediaan['capitalize_conversion']))
                Awal + Buy bahan + tenaga (borongan, atau Gaji Mingguan jika borongan 0) + Material Produksi − COGS.
                HPP produksi = (tenaga + material) ÷ pcs masuk gudang.
            @else
                Awal + Buy bahan − COGS jual − CashOut Gaji Mingguan / material.
            @endif
            @if(!$report['is_consolidated'])
                Nilai closing masuk neraca konsolidasi saja (bukan per entitas).
            @endif
        </p>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Opening</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900" data-testid="persediaan-opening">{{ $fmt($persediaan['opening']) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Buy bahan</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900" data-testid="persediaan-purchases">{{ $fmt($persediaan['material_purchases']) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">COGS jual</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900" data-testid="persediaan-cogs">{{ $fmt($persediaan['cogs']) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Gaji Mingguan</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900" data-testid="persediaan-production">{{ $fmt($persediaan['production_cost']) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">CashOut material</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900" data-testid="persediaan-material-out">{{ $fmt($persediaan['material_cash_out']) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Borongan</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900" data-testid="persediaan-borongan">{{ $fmt($persediaan['borongan_labor'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Pcs produksi (gudang)</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900" data-testid="persediaan-pcs">{{ format_number($persediaan['pcs_manufactured'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">HPP / pcs produksi</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900" data-testid="persediaan-unit-cost">{{ format_amount($persediaan['manufactured_unit_cost'] ?? 0, 4) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">COGS produksi</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900" data-testid="persediaan-cogs-mfg">{{ $fmt($persediaan['manufactured_cogs'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">COGS beli</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900" data-testid="persediaan-cogs-purchased">{{ $fmt($persediaan['purchased_cogs'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Adjustment</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900">{{ $fmt($persediaan['adjustment']) }}</p>
            </div>
            <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                <p class="text-xs text-blue-700">Closing</p>
                <p class="text-lg font-semibold tabular-nums text-blue-900" data-testid="persediaan-closing">{{ $fmt($persediaan['closing']) }}</p>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500" data-testid="persediaan-cogs-note">
            Pcs gudang:
            minggu {{ format_number($persediaan['pcs_manufactured_week'] ?? 0) }},
            bulan {{ format_number($persediaan['pcs_manufactured'] ?? 0) }},
            YTD {{ format_number($persediaan['pcs_manufactured_ytd'] ?? 0) }}.
            Barang yang pernah masuk produksi memakai HPP/pcs;
            barang beli memakai cost item.
            @if(($persediaan['labor_source'] ?? 'none') === 'borongan')
                Tenaga dari borongan (Gaji Mingguan hanya catatan).
            @elseif(($persediaan['labor_source'] ?? 'none') === 'gaji')
                Tenaga dari Gaji Mingguan (borongan 0).
            @endif
            @if(($persediaan['unit_cost_source'] ?? 'none') === 'prior')
                HPP/pcs bulan ini memakai bulan sebelumnya (pcs gudang 0).
            @elseif(($persediaan['unit_cost_source'] ?? 'none') === 'ytd')
                HPP/pcs bulan ini memakai rata-rata YTD (pcs gudang 0).
            @endif
        </p>
    </div>

    @if(abs($report['balance_check']) >= 0.01)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="neraca-imbalance">
            Neraca tidak balance: selisih {{ $fmt($report['balance_check']) }}.
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="neraca-aktiva">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">Aktiva</h3>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    <tr class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <td class="py-2" colspan="2">Aktiva lancar</td>
                    </tr>
                    <tr>
                        <td class="py-2">
                            <button type="button" class="text-left text-blue-700 hover:underline" @click="toggleKas()" data-testid="neraca-kas-toggle">Kas / Bank</button>
                        </td>
                        <td class="py-2 text-right tabular-nums" data-testid="neraca-kas">{{ $fmt($report['aktiva_lancar']['kas']) }}</td>
                    </tr>
                    <tr x-show="kasOpen" x-cloak>
                        <td colspan="2" class="pb-3">
                            <ul class="space-y-1 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                                @forelse($report['drilldown']['kas'] as $row)
                                    <li class="flex justify-between gap-3">
                                        <span>{{ $row['name'] }}@if($row['entity_name']) <span class="text-gray-400">({{ $row['entity_name'] }})</span>@endif</span>
                                        <span class="tabular-nums">{{ $fmt($row['balance']) }}</span>
                                    </li>
                                @empty
                                    <li>Tidak ada saldo bank pada tanggal ini.</li>
                                @endforelse
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2">
                            <button type="button" class="text-left text-blue-700 hover:underline" @click="togglePiutang()">Piutang usaha</button>
                        </td>
                        <td class="py-2 text-right tabular-nums" data-testid="neraca-piutang">{{ $fmt($report['aktiva_lancar']['piutang']) }}</td>
                    </tr>
                    <tr x-show="piutangOpen" x-cloak>
                        <td colspan="2" class="pb-3">
                            <ul class="space-y-1 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                                @forelse($report['drilldown']['piutang'] as $row)
                                    <li class="flex justify-between gap-3">
                                        <span>{{ $row['name'] }}</span>
                                        <span class="tabular-nums">{{ $fmt(abs($row['balance'])) }}</span>
                                    </li>
                                @empty
                                    <li>Tidak ada piutang pada tanggal ini.</li>
                                @endforelse
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2">Persediaan</td>
                        <td class="py-2 text-right tabular-nums" data-testid="neraca-persediaan-line">{{ $fmt($report['aktiva_lancar']['persediaan']) }}</td>
                    </tr>
                    <tr class="font-medium text-gray-700">
                        <td class="py-2">Jumlah aktiva lancar</td>
                        <td class="py-2 text-right tabular-nums">{{ $fmt($report['total_aktiva_lancar']) }}</td>
                    </tr>
                    <tr class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <td class="pt-4 pb-2" colspan="2">Aktiva tetap</td>
                    </tr>
                    <tr>
                        <td class="py-2">Aktiva tetap (nilai buku kini)</td>
                        <td class="py-2 text-right tabular-nums" data-testid="neraca-aktiva-tetap">{{ $fmt($report['aktiva_tetap']) }}</td>
                    </tr>
                    <tr class="border-t border-gray-200 font-semibold text-gray-900">
                        <td class="py-3">Total aktiva</td>
                        <td class="py-3 text-right tabular-nums" data-testid="neraca-total-aktiva">{{ $fmt($report['total_aktiva']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="neraca-pasiva">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">Kewajiban &amp; Ekuitas</h3>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    <tr class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <td class="py-2" colspan="2">Kewajiban</td>
                    </tr>
                    <tr>
                        <td class="py-2">
                            <button type="button" class="text-left text-blue-700 hover:underline" @click="toggleHutang()">Hutang usaha</button>
                        </td>
                        <td class="py-2 text-right tabular-nums" data-testid="neraca-hutang">{{ $fmt($report['kewajiban']['hutang_usaha']) }}</td>
                    </tr>
                    <tr x-show="hutangOpen" x-cloak>
                        <td colspan="2" class="pb-3">
                            <ul class="space-y-1 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                                @forelse($report['drilldown']['hutang'] as $row)
                                    <li class="flex justify-between gap-3">
                                        <span>{{ $row['name'] }}</span>
                                        <span class="tabular-nums">{{ $fmt($row['balance']) }}</span>
                                    </li>
                                @empty
                                    <li>Tidak ada hutang pada tanggal ini.</li>
                                @endforelse
                            </ul>
                        </td>
                    </tr>
                    <tr class="font-medium text-gray-700">
                        <td class="py-2">Jumlah kewajiban</td>
                        <td class="py-2 text-right tabular-nums">{{ $fmt($report['total_kewajiban']) }}</td>
                    </tr>
                    <tr class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <td class="pt-4 pb-2" colspan="2">Ekuitas</td>
                    </tr>
                    <tr>
                        <td class="py-2">Modal</td>
                        <td class="py-2 text-right tabular-nums" data-testid="neraca-modal">{{ $fmt($report['ekuitas']['modal']) }}</td>
                    </tr>
                    @if(abs($report['ekuitas']['laba_ditahan_awal']) >= 0.01)
                    <tr>
                        <td class="py-2">Laba ditahan awal</td>
                        <td class="py-2 text-right tabular-nums">{{ $fmt($report['ekuitas']['laba_ditahan_awal']) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="py-2">Laba ditahan (selisih / plug)</td>
                        <td class="py-2 text-right tabular-nums" data-testid="neraca-laba-ditahan">{{ $fmt($report['ekuitas']['laba_ditahan']) }}</td>
                    </tr>
                    <tr class="border-t border-gray-200 font-semibold text-gray-900">
                        <td class="py-3">Total pasiva</td>
                        <td class="py-3 text-right tabular-nums" data-testid="neraca-total-pasiva">{{ $fmt($report['total_pasiva']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
