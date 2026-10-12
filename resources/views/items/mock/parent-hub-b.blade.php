@extends('layouts.app')

@section('title', 'Mock B (colorway-first): ' . $detail['label'])

@section('content')
@php
$liveParentUrl = route('items.group-parent-detail', $detail['anchor_group_id']);
@endphp

<div class="p-4 sm:p-6">
    @include('items.mock.partials.mock-banner', ['liveUrl' => $liveParentUrl])
    @include('items.mock.partials.mock-variant-nav', [
        'mockSurface' => 'parent',
        'groupId' => $detail['anchor_group_id'],
        'activeVariant' => 'b',
    ])

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4 rounded-xl border border-purple-200 bg-purple-50/40 px-5 py-4">
        <div>
            <p class="text-xs font-semibold uppercase text-purple-800">Style (compact header)</p>
            <h1 class="text-xl font-bold text-gray-900">{{ $detail['uses_placeholder'] ? 'No style title' : $detail['product_name'] }}</h1>
            <p class="font-mono text-xs text-gray-600">{{ $detail['label'] }}</p>
        </div>
        <fieldset disabled class="flex flex-wrap gap-2">
            <button type="button" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">Edit style title &amp; pricing →</button>
        </fieldset>
    </div>

    <p class="mb-3 text-sm text-gray-600">
        <strong>Variant B:</strong> daily work stays on color blocks (like today’s parent page). Style defaults are secondary — one click away, not a separate tab.
    </p>

    <h2 class="mb-3 text-lg font-bold text-gray-900">Colors &amp; catalog titles</h2>
    @include('items.mock.partials.colorway-catalog-table', [
        'colorwayCatalogRows' => $colorwayCatalogRows,
        'canEditGroup' => $canEditGroup,
    ])

    <p class="mt-4 text-xs text-gray-500">Stock / size tables would stay as on the live parent page below this summary (omitted in mock).</p>
</div>
@endsection
