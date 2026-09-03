@extends('layouts.app')

@section('title', 'Archive — Item #' . $item->id)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Archive', 'href' => route('archive.index')],
    ['title' => 'Items', 'href' => route('archive.items.index')],
    ['title' => $item->code, 'href' => route('archive.items.show', $item->id)],
];
$idr = fn ($v) => 'Rp ' . format_amount($v, 0);
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex items-start justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight">{{ $item->name }}</h1>
                <span class="rounded-md bg-slate-700 px-2 py-0.5 text-xs font-semibold uppercase text-white">Read-only</span>
            </div>
            <p class="font-mono text-sm text-gray-500">{{ $item->code }} @if($item->legacy_code)<span class="text-gray-400">· legacy {{ $item->legacy_code }}</span>@endif</p>
        </div>
        <a href="{{ route('archive.items.index') }}" class="text-sm font-medium text-blue-600 hover:underline">← Back</a>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Identity</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-gray-500">ID</dt><dd class="font-mono">#{{ $item->id }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Pcode</dt><dd class="font-mono">{{ $item->pcode ?: '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Group</dt><dd>{{ $item->group?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Type</dt><dd>{{ $item->type?->name ?? $item->type }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Created</dt><dd>{{ $item->created_at?->format('d/m/Y') ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-xl border bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Pricing</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Price</dt><dd class="tabular-nums">{{ $idr($item->price) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Cost</dt><dd class="tabular-nums">{{ $idr($item->cost) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Qty (header)</dt><dd class="tabular-nums">{{ $item->qty }}</dd></div>
            </dl>
        </div>
    </div>

    @if($item->catalogDescription() || $item->catalogDescription2())
    <div class="rounded-xl border bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Description</h2>
        @if($item->catalogDescription())<p class="mt-2 whitespace-pre-wrap text-sm">{{ $item->catalogDescription() }}</p>@endif
        @if($item->catalogDescription2())<p class="mt-2 whitespace-pre-wrap text-sm text-gray-600">{{ $item->catalogDescription2() }}</p>@endif
    </div>
    @endif
</div>
@endsection
