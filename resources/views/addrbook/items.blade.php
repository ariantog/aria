@extends('layouts.app')

@section('title', 'Items: ' . $addrbook->name)

@section('content')
@php
$baseUrl = '/' . $addrbook->type_slug . '/' . $addrbook->id . '/items';
$exportUrl = '/' . $addrbook->type_slug . '/' . $addrbook->id . '/items/export';
$exportQuery = request()->query();
$perPage = $perPage ?? (int) request()->query('per_page', 1000);
$breadcrumbs = [
    ['title' => 'Address Book', 'href' => \App\Models\Addrbook::typeIndexRoute($addrbook->type_slug)],
    ['title' => $addrbook->name, 'href' => '/' . $addrbook->type_slug . '/' . $addrbook->id],
    ['title' => 'Items', 'href' => $baseUrl],
];
$idr = fn ($v) => 'IDR ' . format_amount($v, 0);
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4" x-data="{ showImage: false, onlineName: false }">
    {{-- Header --}}
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <a href="/{{ $addrbook->type_slug }}/{{ $addrbook->id }}" class="text-gray-400 hover:text-gray-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="font-mono text-sm text-gray-400">#{{ $addrbook->id }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Warehouse Stock</h1>
            <p class="text-sm text-gray-500">Available inventory for <span class="text-blue-600">{{ $addrbook->name }}</span></p>
            @if($jubelioSync ?? null)
                <p class="mt-1 text-xs text-gray-500">
                    Jubelio location:
                    <span class="font-medium text-gray-700">{{ $jubelioSync->jubelio_location_name }}</span>
                    <span class="text-gray-400">· on-hand stock at mapped location</span>
                </p>
            @endif
        </div>
    </div>

    @include('addrbook.partials.tabs', ['active' => 'items'])

    @if(($jubelioUnlinkedCount ?? 0) > 0)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>{{ $jubelioUnlinkedCount }}</strong> item(s) on this page are not linked to Jubelio (missing Jubelio item ID).
        </div>
    @endif

    @if(($jubelioFetchFailed ?? false) && ($jubelioSync ?? null))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            Could not fetch Jubelio stock right now. Aria stock is still shown below.
        </div>
    @endif

    {{-- Filters --}}
    <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4">
        <form method="GET" action="{{ $baseUrl }}" class="flex flex-wrap items-end gap-2 border-b border-gray-100 pb-4">
            <div class="flex flex-1 min-w-[220px] flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">Item Name / Code</label>
                <div class="relative">
                    <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Search…" class="w-full rounded-md border border-gray-300 py-1.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium uppercase text-gray-500">Sort By</label>
                <select name="sort" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    @foreach(['qtydesc' => 'Quantity (High to Low)', 'qtyasc' => 'Quantity (Low to High)', 'namedesc' => 'Name (Z-A)', 'nameasc' => 'Name (A-Z)', 'codedesc' => 'Code (Z-A)', 'codeasc' => 'Code (A-Z)'] as $val => $lbl)
                        <option value="{{ $val }}" @selected(($filters['sort'] ?? 'qtydesc') === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2 py-2">
                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-600">
                    <input type="checkbox" name="show0" value="show" @checked(($filters['show0'] ?? '') === 'show') class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    Show empty stock (&lt; 1)
                </label>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Apply Filter</button>
                <a href="{{ $baseUrl }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
            </div>
        </form>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
            <span class="text-[10px] font-bold uppercase text-gray-500">Display Options:</span>
            <button type="button" @click="showImage = !showImage"
                    :class="showImage ? 'border-blue-500/20 bg-blue-50 text-blue-600' : 'border-gray-200 text-gray-500'"
                    class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium">
                <span x-text="showImage ? 'Hide Image' : 'Show Image'"></span>
            </button>
            <div class="flex rounded-lg border border-gray-200 bg-gray-50 p-1">
                <button type="button" @click="onlineName = false" :class="!onlineName ? 'bg-gray-800 text-white' : 'text-gray-500'" class="rounded-md px-3 py-1 text-[10px] font-bold uppercase">Normal Name</button>
                <button type="button" @click="onlineName = true" :class="onlineName ? 'bg-gray-800 text-white' : 'text-gray-500'" class="rounded-md px-3 py-1 text-[10px] font-bold uppercase">Online Name</button>
            </div>
            </div>
            <a href="{{ $exportUrl . (count($exportQuery) ? '?' . http_build_query($exportQuery) : '') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export Excel
            </a>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[1100px] text-left text-xs">
            <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="w-14 px-2 py-2.5 font-bold">ID</th>
                    <th class="w-16 px-2 py-2.5 font-bold" x-show="showImage">Image</th>
                    <th class="whitespace-nowrap px-2 py-2.5 font-bold">Item Name</th>
                    <th class="min-w-[7rem] px-2 py-2.5 font-bold">Code</th>
                    <th class="min-w-[8rem] max-w-[14rem] px-2 py-2.5 font-bold">Description</th>
                    <th class="whitespace-nowrap px-2 py-2.5 text-right font-bold">Price</th>
                    <th class="whitespace-nowrap px-2 py-2.5 text-right font-bold">Stock</th>
                    @if($jubelioSync ?? null)
                        <th class="whitespace-nowrap px-2 py-2.5 text-right font-bold">Jubelio</th>
                    @endif
                    <th class="w-14 px-2 py-2.5 text-center font-bold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($items as $item)
                    @php
                        $g = $item->group;
                        $normalName = $g->description ?? $item->name;
                        $onlineNm = $g->description2 ?? $g->description ?? $item->name;
                        $desc = $item->description ?: ($g->description ?? '-');
                        $qty = (float) ($item->pivot->quantity ?? 0);
                        $jubelio = ($jubelioStocks ?? [])[$item->id] ?? null;
                        $itemShowUrl = $item->showUrl();
                        $itemEditUrl = $item->editUrl();
                    @endphp
                    <tr class="cursor-pointer align-top hover:bg-gray-50" onclick="window.location='{{ $itemShowUrl }}'">
                        <td class="px-2 py-2 font-mono">
                            <a href="{{ $itemShowUrl }}" onclick="event.stopPropagation()" class="text-blue-600 hover:underline">#{{ $item->id }}</a>
                        </td>
                        <td class="px-2 py-2" x-show="showImage">
                            <img src="{{ $item->image_url ?: '/images/default-item.png' }}" onerror="this.src='/images/default-item.png'" class="h-10 w-10 rounded-md border border-gray-200 object-cover">
                        </td>
                        <td class="min-w-[10rem] max-w-[16rem] px-2 py-2">
                            <a href="{{ $itemShowUrl }}" onclick="event.stopPropagation()" class="block truncate font-medium text-gray-800 hover:text-blue-600" title="{{ $onlineName ?? $normalName }}">
                                <span x-show="!onlineName">{{ $normalName }}</span>
                                <span x-show="onlineName" x-cloak>{{ $onlineNm }}</span>
                            </a>
                            <div class="truncate font-mono text-[10px] text-gray-400">{{ $item->name }}</div>
                        </td>
                        <td class="truncate px-2 py-2 font-mono">
                            <a href="{{ $itemShowUrl }}" onclick="event.stopPropagation()" class="text-blue-600 hover:underline" title="{{ $item->code }}">{{ $item->code }}</a>
                        </td>
                        <td class="min-w-[8rem] max-w-[14rem] break-words px-2 py-2 text-gray-500 whitespace-pre-line">{{ $desc }}</td>
                        <td class="whitespace-nowrap px-2 py-2 text-right font-semibold text-gray-700">{{ $idr($item->price) }}</td>
                        <td class="whitespace-nowrap px-2 py-2 text-right font-mono font-bold {{ $qty > 0 ? 'text-emerald-600' : 'text-gray-400' }}">{{ format_amount($qty, 0) }}</td>
                        @if($jubelioSync ?? null)
                            <td class="whitespace-nowrap px-2 py-2 text-right">
                                @if(! $jubelio || ! ($jubelio['linked'] ?? false))
                                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700" title="Item is not linked to Jubelio">
                                        Not linked
                                    </span>
                                @elseif($jubelio['on_hand'] !== null)
                                    <span class="font-mono font-bold {{ ($jubelio['mismatch'] ?? false) ? 'text-red-600' : 'text-blue-600' }}" title="Jubelio on-hand at {{ $jubelioSync->jubelio_location_name }}">
                                        {{ format_amount($jubelio['on_hand'], 0) }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endif
                        <td class="px-2 py-2 text-center">
                            <a href="{{ $itemEditUrl }}" onclick="event.stopPropagation()" class="inline-flex h-8 w-8 items-center justify-center rounded text-gray-500 hover:bg-blue-50 hover:text-blue-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ ($jubelioSync ?? null) ? 9 : 8 }}" class="px-4 py-12 text-center text-sm italic text-gray-500">No items found in this warehouse.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.pagination', ['paginator' => $items, 'label' => 'items'])
    </div>
</div>
@endsection
