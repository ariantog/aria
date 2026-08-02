@extends('layouts.app')

@section('title', 'Borongan List')

@section('content')
@php
$breadcrumbs = [['title' => 'Borongan', 'href' => route('borongan.index')]];
$fmt = fn($v) => number_format((float)($v ?? 0), 0, ',', '.');
$f = $filters;
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight">Borongan List</h1>
        @if($can['create_borongan'])
        <a href="{{ route('borongan.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Borongan
        </a>
        @endif
    </div>

    <form method="GET" action="{{ route('borongan.index') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-col gap-1"><label class="text-xs font-medium uppercase text-gray-500">From</label><input type="date" name="from" value="{{ $f['from'] ?? '' }}" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm"></div>
        <div class="flex flex-col gap-1"><label class="text-xs font-medium uppercase text-gray-500">To</label><input type="date" name="to" value="{{ $f['to'] ?? '' }}" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm"></div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Penjahit</label>
            <select name="jahit_id" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">Semua Penjahit</option>
                @foreach($jahitList as $j)
                <option value="{{ $j->id }}" @selected((string)($f['jahit_id'] ?? '') === (string)$j->id)>{{ $j->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
            <a href="{{ route('borongan.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-100">
                    <tr class="text-xs uppercase text-gray-500">
                        <th class="h-12 px-4 font-medium">Date</th>
                        <th class="h-12 px-4 font-medium">Jahit</th>
                        <th class="h-12 px-4 font-medium">Items</th>
                        <th class="h-12 px-4 font-medium">Tres</th>
                        <th class="h-12 px-4 font-medium">Permak</th>
                        <th class="h-12 px-4 font-medium">Lain2</th>
                        <th class="h-12 px-4 font-medium">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($borongans as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="p-4">
                            @if($can['view_borongan'])
                            <a href="{{ route('borongan.show', $item->id) }}" class="font-medium text-blue-600 hover:underline">{{ $item->date ? \Carbon\Carbon::parse($item->date)->format('Y-m-d') : '-' }}</a>
                            @else
                            <span class="font-medium">{{ $item->date ? \Carbon\Carbon::parse($item->date)->format('Y-m-d') : '-' }}</span>
                            @endif
                        </td>
                        <td class="p-4">{{ $item->jahit->name ?? '-' }}</td>
                        <td class="p-4">{{ $item->total_items }}</td>
                        <td class="p-4">{{ $fmt($item->tres) }}</td>
                        <td class="p-4">{{ $fmt($item->permak) }}</td>
                        <td class="p-4">{{ $fmt($item->lain2) }}</td>
                        <td class="p-4 font-semibold">{{ $fmt($item->total) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="p-4 text-center text-gray-500">Data Empty</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $borongans, 'label' => 'borongan'])
    </div>
</div>
@endsection
