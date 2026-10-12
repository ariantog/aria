@extends('layouts.app')

@section('title', 'Mock SKU: ' . $item->code)

@section('content')
@php
$liveEditUrl = $item->editUrl();
$effectiveTitle = $titleForm['effective_title'] ?? '';
@endphp

<div class="p-4 sm:p-6">
    @include('items.mock.partials.mock-banner', ['liveUrl' => $liveEditUrl])
    @include('items.mock.partials.mock-variant-nav', [
        'mockSurface' => 'sku',
        'itemId' => $item->id,
        'activeVariant' => $activeVariant ?? 'a',
    ])

    @if(($activeVariant ?? 'a') === 'b')
    <p class="mb-4 text-sm text-gray-600"><strong>Variant B:</strong> SKU page shows inheritance chain before size-only fields.</p>
    <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50/50 p-4 text-sm text-blue-950">
        <p class="font-medium">Display name preview</p>
        <p class="mt-1 font-mono">{{ $item->name }}</p>
        <ul class="mt-2 list-inside list-disc text-blue-900">
            <li>Style title: {{ $parentProductName !== '' ? $parentProductName : '—' }}</li>
            <li>Colorway title: {{ $titleForm['stored_title'] !== '' ? $titleForm['stored_title'] : '(inherit)' }}</li>
            <li>Effective bare title: <strong>{{ $effectiveTitle }}</strong></li>
        </ul>
    </div>
    @else
    <p class="mb-4 text-sm text-gray-600"><strong>Variant A:</strong> matches current SKU edit — catalog links out; only price/restock here.</p>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-5" data-testid="mock-sku-panel">
        <h3 class="text-lg font-semibold text-gray-900">Catalog &amp; pricing (SKU scope)</h3>
        <p class="mt-1 text-xs text-gray-500">Shared catalog lives on group pages — same as live sku-only panel.</p>
        <ul class="mt-4 list-inside list-disc text-sm text-indigo-900">
            @if($parentGroupUrl)
            <li><a href="{{ $parentGroupUrl }}" class="underline">Parent group</a> — style title defaults</li>
            @endif
            @if($colorwayEditUrl)
            <li><a href="{{ $colorwayEditUrl }}" class="underline">Colorway editor</a> — pcode, colorway title, matrix</li>
            @endif
        </ul>
        <fieldset disabled class="mt-4 grid max-w-md gap-3">
            <label class="text-sm">Restock threshold (this size)</label>
            <input type="text" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" value="(mock)">
            <label class="text-sm">Sell price override (this size)</label>
            <input type="text" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" value="(mock)">
        </fieldset>
    </div>
</div>
@endsection
