@extends('layouts.app')

@section('title', 'History - ' . ($restock->item->name ?? 'Restock'))

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
    ['title' => 'History', 'href' => '#'],
];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="mb-4">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-zinc-900">History: {{ $restock->item->name ?? '' }}</h1>
        <p class="text-zinc-500">Detailed log of all stock movements and updates for <span class="font-medium text-zinc-700">{{ $restock->item->code ?? '' }}</span>.</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="px-6 py-4 font-bold tracking-wider">Date</th>
                        <th class="px-6 py-4 font-bold tracking-wider">Step / Action</th>
                        <th class="px-6 py-4 text-right font-bold tracking-wider">Qty Before</th>
                        <th class="px-6 py-4 text-right font-bold tracking-wider">Qty Changed</th>
                        <th class="px-6 py-4 text-right font-bold tracking-wider">Qty After</th>
                        <th class="px-6 py-4 font-bold tracking-wider">User / Invoice</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse($histories as $log)
                    <tr class="transition-colors hover:bg-zinc-50">
                        <td class="whitespace-nowrap px-6 py-4">
                            <div class="font-medium text-zinc-900">{{ \Carbon\Carbon::parse($log->date)->translatedFormat('d M Y') }}</div>
                            <div class="font-mono text-[10px] text-zinc-500">{{ $log->created_at?->format('H:i:s') }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-1">
                                <span class="h-4 w-fit rounded border border-gray-300 px-1 py-0 text-[9px] font-bold uppercase">{{ $log->step }}</span>
                                <span class="text-xs italic text-zinc-500">{{ $log->action }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right font-mono text-zinc-500">{{ $log->qty_before }}</td>
                        <td class="px-6 py-4 text-right font-mono font-bold {{ $log->qty_changed >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $log->qty_changed > 0 ? '+' . $log->qty_changed : $log->qty_changed }}</td>
                        <td class="px-6 py-4 text-right font-mono text-zinc-900">{{ $log->qty_after }}</td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <div class="text-xs font-medium text-zinc-900">{{ $log->user->name ?? 'System' }}</div>
                            @if($log->invoice)<div class="flex items-center gap-1 text-[10px] text-zinc-500"><span class="opacity-50">Ref:</span> {{ $log->invoice }}</div>@endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center italic text-zinc-400">No history found for this item.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $histories, 'label' => 'history records'])
    </div>
</div>
@endsection
