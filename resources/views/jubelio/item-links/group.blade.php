@extends('layouts.app')

@section('title', 'Jubelio Links: ' . $detail['label'])

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio', 'href' => route('jubelio.index')],
    ['title' => 'Item Links', 'href' => route('jubelio.item-links.index')],
    ['title' => $detail['label'], 'href' => '#'],
];
@endphp

<div class="p-4 sm:p-6">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('jubelio.item-links.index') }}" class="text-sm text-blue-600 hover:underline">← Kembali ke daftar grup</a>
            <h1 class="mt-2 text-2xl font-bold text-gray-900">{{ $detail['label'] }}</h1>
            <p class="text-sm text-gray-500">{{ $detail['product_name'] ?? '' }}</p>
        </div>
        <div class="flex flex-wrap gap-3 text-sm">
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-2">
                <span class="text-gray-500">Total SKU</span>
                <span class="ml-2 font-bold text-gray-900">{{ $stats['total'] }}</span>
            </div>
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-2">
                <span class="text-green-700">Linked</span>
                <span class="ml-2 font-bold text-green-900">{{ $stats['linked'] }}</span>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2">
                <span class="text-amber-800">Unlinked</span>
                <span class="ml-2 font-bold text-amber-900">{{ $stats['unlinked'] }}</span>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3 font-bold">Color</th>
                        <th class="px-6 py-3 font-bold">Size</th>
                        <th class="px-6 py-3 font-bold">SKU</th>
                        <th class="px-6 py-3 font-bold">Jubelio ID</th>
                        <th class="px-6 py-3 font-bold">Status</th>
                        <th class="px-6 py-3 font-bold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                    <tr class="hover:bg-gray-50/50 {{ $row['linked'] ? '' : 'bg-amber-50/30' }}">
                        <td class="px-6 py-3 text-gray-700">{{ $row['color_name'] ?: $row['color_code'] ?: '—' }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ $row['size'] }}</td>
                        <td class="px-6 py-3 font-mono">
                            <a href="{{ $row['show_url'] }}" class="text-blue-600 hover:underline">{{ $row['code'] }}</a>
                        </td>
                        <td class="px-6 py-3 font-mono text-gray-600">{{ $row['jubelio_item_id'] ?? '—' }}</td>
                        <td class="px-6 py-3">
                            @if($row['linked'])
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Linked</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Not linked</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ $row['jubelio_url'] }}" class="text-xs font-medium text-blue-600 hover:underline">Link</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">Tidak ada SKU di grup ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
