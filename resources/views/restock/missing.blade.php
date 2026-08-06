@extends('layouts.app')

@section('title', 'Missing SKUs — Restock')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
    ['title' => 'Missing SKUs', 'href' => $activeTypeTag
        ? route('restock.type.missing', $activeTypeTag)
        : route('restock.missing.index')],
];
@endphp

<div class="flex flex-col gap-4 p-4">
    @include('restock.partials.type-tabs', [
        'typeTags' => $typeTags,
        'activeTypeTag' => $activeTypeTag,
    ])

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Missing SKUs</h1>
            <p class="text-sm text-gray-500">
                @if($activeTypeTag)
                    Shortfalls from receive for {{ $activeTypeTag->name }}.
                @else
                    Shortfalls from receive across all asset lancar types.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('restock.settings.edit') }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Settings
            </a>
            <a href="{{ $activeTypeTag ? route('restock.type.show', $activeTypeTag) : route('restock.index') }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Back to restock
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if($rows->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">
            No missing SKUs{{ $activeTypeTag ? " for {$activeTypeTag->name}" : '' }}.
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        @unless($activeTypeTag)
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Type</th>
                        @endunless
                        <th class="px-4 py-3 text-left font-medium text-gray-600">SKU</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Name</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">Qty missing</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">Missing since</th>
                        @if($canEdit)
                            <th class="px-4 py-3 text-right font-medium text-gray-600"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                        <tr class="hover:bg-gray-50">
                            @unless($activeTypeTag)
                                <td class="px-4 py-3 text-gray-700">{{ $row['type_name'] }}</td>
                            @endunless
                            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $row['sku_code'] }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $row['sku_name'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-red-700">{{ number_format($row['qty_missing']) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $row['missing_at'] ?? '—' }}</td>
                            @if($canEdit)
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('restock.missing.found', $row['cell_id']) }}"
                                          onsubmit="return confirm('Mark this SKU as found? Missing qty will be cleared.');">
                                        @csrf
                                        <button type="submit"
                                                class="font-medium text-green-700 hover:text-green-900">
                                            Mark found
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
