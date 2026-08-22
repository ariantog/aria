@extends('layouts.app')

@section('title', 'Transaction Sync')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Transaction Sync', 'href' => route('jubelio.transaction.sync')],
];
$activeType = $filters['type'] ?? '';
$activeDisplay = $filters['display'] ?? 'N';
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <h1 class="flex items-center gap-2 text-2xl font-bold">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Transaction Sync
        </h1>

        <form method="GET" action="{{ route('jubelio.transaction.sync') }}" class="flex flex-wrap items-end gap-2">
            <div class="flex flex-col gap-1">
                <label class="ml-1 text-[10px] font-bold uppercase text-gray-400">Date</label>
                <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="h-9 w-40 rounded-md border border-gray-300 px-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex flex-col gap-1">
                <label class="ml-1 text-[10px] font-bold uppercase text-gray-400">Invoice</label>
                <div class="relative">
                    <svg class="absolute top-2.5 left-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    <input type="text" name="invoice" value="{{ $filters['invoice'] ?? '' }}" placeholder="Search Invoice..." class="h-9 w-48 rounded-md border border-gray-300 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="ml-1 text-[10px] font-bold uppercase text-gray-400">Type</label>
                <select name="type" class="h-9 w-40 rounded-md border border-gray-300 px-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">All Types</option>
                    @foreach($types as $id => $name)
                    <option value="{{ $id }}" {{ (string)$activeType === (string)$id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="ml-1 text-[10px] font-bold uppercase text-gray-400">Display</label>
                <select name="display" class="h-9 w-24 rounded-md border border-gray-300 px-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="N" {{ $activeDisplay === 'N' ? 'selected' : '' }}>Show</option>
                    <option value="Y" {{ $activeDisplay === 'Y' ? 'selected' : '' }}>Hidden</option>
                </select>
            </div>
            <button type="submit" class="h-9 rounded-lg bg-blue-700 px-4 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
            <a href="{{ route('jubelio.transaction.sync') }}" class="inline-flex h-9 items-center gap-1 rounded-lg px-3 text-sm font-medium text-gray-600 hover:bg-gray-100">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Clear
            </a>
        </form>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 font-semibold text-gray-600 uppercase">
                    <tr>
                        <th class="px-6 py-4">Date</th>
                        <th class="max-w-[150px] px-6 py-4">Invoice</th>
                        <th class="px-6 py-4">Type</th>
                        <th class="max-w-[150px] px-6 py-4">Description</th>
                        <th class="px-6 py-4">Sender</th>
                        <th class="px-6 py-4">Receiver</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($transactions as $item)
                    @php
                        $senderNeeds = in_array($item->sync_cek, ['S', 'B']);
                        $receiverNeeds = in_array($item->sync_cek, ['R', 'B']);
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-bold text-gray-800">{{ \Carbon\Carbon::parse($item->date)->translatedFormat('d M Y') }}</span>
                        </td>
                        <td class="max-w-[150px] px-6 py-4 font-bold break-words text-blue-600">
                            <a href="{{ route('jubelio.transaction.detail-sync', $item->id) }}" class="hover:underline">{{ $item->invoice }}</a>
                        </td>
                        <td class="px-6 py-4 text-[10px] font-bold whitespace-nowrap uppercase">
                            <span class="inline-flex rounded border border-gray-200 bg-white px-2 py-0.5">{{ $item->type_name }}</span>
                        </td>
                        <td class="max-w-[150px] px-6 py-4 text-[10px] leading-tight break-words text-gray-500 italic">{{ $item->description }}</td>
                        <td class="px-6 py-4">
                            @include('jubelio.partials.side-status', ['name' => $item->sender->name ?? 'Unknown', 'submitted' => !empty($item->a_submit_by), 'needsSync' => $senderNeeds, 'role' => 'sender'])
                        </td>
                        <td class="px-6 py-4">
                            @include('jubelio.partials.side-status', ['name' => $item->receiver->name ?? 'Unknown', 'submitted' => !empty($item->b_submit_by), 'needsSync' => $receiverNeeds, 'role' => 'receiver'])
                        </td>
                        <td class="px-6 py-4 text-right">
                            <form method="POST" action="{{ route('jubelio.transaction.sync-display', $item->id) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                @if($item->sync_hide === 'N')
                                <button type="submit" class="inline-flex h-8 items-center gap-1 rounded-md bg-red-600 px-3 text-[10px] font-bold uppercase text-white hover:bg-red-700">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                    Hide
                                </button>
                                @else
                                <button type="submit" class="inline-flex h-8 items-center gap-1 rounded-md bg-gray-200 px-3 text-[10px] font-bold uppercase text-gray-700 hover:bg-gray-300">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Show
                                </button>
                                @endif
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center font-medium text-gray-400 italic">No transactions pending synchronization.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $transactions, 'label' => 'transactions'])
    </div>
</div>
@endsection
