@extends('layouts.app')

@section('title', $title)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Produksi', 'href' => route('produksi.index')],
    ['title' => ucfirst($type).' Workers', 'href' => route('produksi.'.$type.'.index')],
    ['title' => $worker->name, 'href' => route('produksi.'.$type.'.show', $worker->id)],
];
$months = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col items-start justify-between gap-4 border-b border-gray-200 pb-4 sm:flex-row sm:items-center">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ ucfirst($type) }} Worker</p>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $worker->name }}</h2>
            <p class="mt-1 text-sm text-gray-500">Joined {{ $worker->created_at?->format('d M Y') }}</p>
        </div>
        <a href="{{ route('produksi.'.$type.'.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">Back to list</a>
    </div>

    <form method="GET" action="{{ route('produksi.'.$type.'.show', $worker->id) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Month</label>
            <select name="month" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                <option value="">Full year</option>
                @foreach($months as $num => $label)
                <option value="{{ $num }}" @selected(($filters['month'] ?? '') == $num)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Year</label>
            <select name="year" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                @foreach($years as $y)
                <option value="{{ $y }}" @selected(($filters['year'] ?? $year) == $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
    </form>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-500">Kitir</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($stats->kitir_count) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-500">Total Qty</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($stats->total_qty) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-500">Avg Qty</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats->avg_qty }}</p>
        </div>
        @if($type === 'potong')
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-500">SJP / Kode</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats->sjp_count }} / {{ $stats->kode_count }}</p>
        </div>
        @elseif($type === 'qc')
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-500">Avg lag (potong→QC)</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats->avg_potong_lag_days ?? '—' }}<span class="text-sm font-normal text-gray-500"> hari</span></p>
        </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-4 py-3">
            <h3 class="text-sm font-bold uppercase text-gray-900">History</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        @foreach(['Serial','Kode','Qty','SJP','Customer','Status'] as $h)
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-900 whitespace-nowrap">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($history as $row)
                    @php
                        $editRoute = $row->status === \App\Models\Produksi::STATUS_PRODUKSI
                            ? route('produksi.edit', $row->id)
                            : route('produksi.setoran.edit', $row->id);
                    @endphp
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 font-bold text-blue-600 whitespace-nowrap"><a href="{{ $editRoute }}" class="hover:underline">{{ $row->serial }}</a></td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $row->item->item_code ?? $row->temp_name }}</td>
                        <td class="px-4 py-3 font-bold whitespace-nowrap">{{ $row->quantity }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $row->surat_jalan_potong ?: '-' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $row->customer ?: '-' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @php $statusLabel = \App\Models\Produksi::statusLabel((int) $row->status); @endphp
                            {{ $statusLabel }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">No records in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $history, 'label' => 'records'])
    </div>
</div>
@endsection
