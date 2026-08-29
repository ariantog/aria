@php
$fmt = fn ($v) => format_amount($v);
$monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$kindLabel = $report['kind'] === \App\Services\Reporting\AgingReportService::KIND_PAYABLE ? 'Hutang Usaha' : 'Piutang Usaha';
$routeName = $report['kind'] === \App\Services\Reporting\AgingReportService::KIND_PAYABLE ? 'reports.payables' : 'reports.receivables';
$pageTestId = $report['kind'] === \App\Services\Reporting\AgingReportService::KIND_PAYABLE ? 'payables-page' : 'receivables-page';
$exportQuery = http_build_query([
    'year' => $filters['year'],
    'month' => $filters['month'],
    'entity' => $filters['entity'],
    'export' => 'csv',
]);
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
        openId: null,
        toggle(id) { this.openId = this.openId === id ? null : id },
        isOpen(id) { return this.openId === id },
    }"
    data-testid="{{ $pageTestId }}"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">{{ $kindLabel }}</h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ $report['entity_label'] }} — umur {{ $monthNames[$filters['month']] }} {{ $filters['year'] }}
                (as of {{ $report['as_of'] }}, {{ $report['source'] === 'snapshot' ? 'snapshot' : 'replay as-of' }})
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route($routeName) }}?{{ $refreshQuery }}"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                data-testid="aging-refresh"
            >
                Recalculate as-of
            </a>
            <a
                href="{{ route($routeName) }}?{{ $exportQuery }}"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                data-testid="aging-export-csv"
            >
                Export CSV
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route($routeName) }}" class="flex flex-wrap items-end gap-4">
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
                <select id="entity" name="entity" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm" data-testid="aging-entity">
                    <option value="{{ \App\Services\Reporting\AgingReportService::CONSOLIDATED_ENTITY }}" @selected((int) $filters['entity'] === \App\Services\Reporting\AgingReportService::CONSOLIDATED_ENTITY)>
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
                <a href="{{ route($routeName) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</a>
            </div>
        </form>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach(['0-30' => '0–30 hari', '31-60' => '31–60 hari', '61-90' => '61–90 hari', '90+' => '90+ hari'] as $bucket => $label)
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                <p class="text-xs text-gray-500">{{ $label }}</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900" data-testid="aging-total-{{ $bucket }}">{{ $fmt($report['totals'][$bucket]) }}</p>
            </div>
        @endforeach
        <div class="rounded-lg border border-blue-100 bg-blue-50 p-3 shadow-sm">
            <p class="text-xs text-blue-700">Total usaha</p>
            <p class="text-lg font-semibold tabular-nums text-blue-900" data-testid="aging-outstanding">{{ $fmt($report['outstanding_total']) }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full min-w-[800px] text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-2 text-left">Kontak</th>
                    <th class="px-4 py-2 text-left">Due day</th>
                    <th class="px-4 py-2 text-right">0–30</th>
                    <th class="px-4 py-2 text-right">31–60</th>
                    <th class="px-4 py-2 text-right">61–90</th>
                    <th class="px-4 py-2 text-right">90+</th>
                    <th class="px-4 py-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($report['rows'] as $row)
                    <tr>
                        <td class="px-4 py-2">
                            <button type="button" class="text-left text-blue-700 hover:underline" @click="toggle({{ $row['id'] }})" data-testid="aging-row-{{ $row['id'] }}">
                                {{ $row['name'] }}
                            </button>
                            @if($row['entity_name'])
                                <p class="text-xs text-gray-400">{{ $row['entity_name'] }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ $row['payment_due_day'] ?? '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $fmt($row['buckets']['0-30']) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $fmt($row['buckets']['31-60']) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $fmt($row['buckets']['61-90']) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $fmt($row['buckets']['90+']) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums font-medium">{{ $fmt($row['outstanding']) }}</td>
                    </tr>
                    <tr x-show="isOpen({{ $row['id'] }})" x-cloak>
                        <td colspan="7" class="bg-gray-50 px-4 py-3">
                            <ul class="space-y-1 text-xs text-gray-600">
                                @forelse($row['invoices'] as $invoice)
                                    <li class="flex justify-between gap-3">
                                        <span>
                                            @if(!empty($invoice['unallocated']))
                                                Saldo awal / tidak teralokasi
                                            @else
                                                {{ $invoice['date'] }}
                                                {{ $invoice['invoice'] ?? ('tx #'.$invoice['id']) }}
                                                <span class="text-gray-400">jatuh tempo {{ $invoice['due_date'] }} ({{ $invoice['days'] }} hari, {{ $invoice['bucket'] }})</span>
                                            @endif
                                        </span>
                                        <span class="tabular-nums">{{ $fmt($invoice['open_amount']) }}</span>
                                    </li>
                                @empty
                                    <li>Tidak ada invoice terbuka.</li>
                                @endforelse
                            </ul>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">Tidak ada saldo usaha pada tanggal ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
