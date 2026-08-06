@extends('layouts.app')

@section('title', $sheet->name.' — Restock')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
    ['title' => $sheet->typeTag->name, 'href' => route('restock.type.show', $sheet->typeTag)],
    ['title' => $sheet->name, 'href' => route('restock.sheets.show', $sheet)],
];
$totalCells = $parentGroups->flatten()->count();
@endphp

<div class="flex flex-col gap-4 p-4">
    @include('restock.partials.type-tabs', [
        'typeTags' => $typeTags,
        'activeTypeTag' => $sheet->typeTag,
    ])

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-4">
            <img src="{{ $sheet->image_url }}" alt="" class="h-16 w-16 rounded-lg border border-gray-200 object-cover">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $sheet->name }}</h1>
                <p class="text-sm text-gray-500">
                    {{ $parentGroups->count() }} parent{{ $parentGroups->count() === 1 ? '' : 's' }}
                    · {{ $totalCells }} SKU cells
                </p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('restock-edit')
            <form method="POST" action="{{ route('restock.sheets.sync', $sheet) }}">
                @csrf
                <button type="submit" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Sync SKUs
                </button>
            </form>
            @endcan
            <a href="{{ route('restock.type.show', $sheet->typeTag) }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Back to list
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        Tabulator grid (restock / production / shipped) ships in the next PR. Below is the seeded cell data grouped by parent pcode (e.g. BELT-01, BELT-02).
    </div>

    @forelse($parentGroups as $parentPcode => $cells)
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                <h2 class="font-semibold text-gray-900">{{ $parentPcode }}</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">SKU</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Color</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Size</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600">Restock</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600">Production</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600">Shipped</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-600">Urgent</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($cells as $cell)
                        <tr class="{{ $cell->is_urgent ? 'bg-red-50' : '' }}">
                            <td class="px-4 py-2 font-mono text-xs text-gray-700">{{ $cell->item->code }}</td>
                            <td class="px-4 py-2">{{ $cell->color?->name ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $cell->size?->code ?? '—' }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $cell->qty_restock }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $cell->qty_production }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $cell->qty_shipped }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($cell->is_urgent)
                                    <span class="font-medium text-red-600">Yes</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">
            No cells seeded. Try Sync SKUs.
        </div>
    @endforelse
</div>
@endsection
