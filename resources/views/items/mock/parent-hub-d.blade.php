@extends('layouts.app')

@section('title', 'Mock D (live + labels): ' . $detail['label'])

@section('content')
@php
$liveParentUrl = route('items.group-parent-detail', $detail['anchor_group_id']);
@endphp

<div class="p-4 sm:p-6" x-data="{ parentEditOpen: false }">
    @include('items.mock.partials.mock-banner', ['liveUrl' => $liveParentUrl])
    @include('items.mock.partials.mock-variant-nav', [
        'mockSurface' => 'parent',
        'groupId' => $detail['anchor_group_id'],
        'activeVariant' => 'd',
    ])

    <p class="mb-4 text-sm text-gray-600">
        <strong>Variant D:</strong> same structure as the <em>live</em> parent page (image, collapsible edit, color sections) — only copy/labels change. Closest to “current style OK”.
    </p>

    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="flex min-h-[200px] items-center justify-center rounded-xl border border-gray-200 bg-white p-4">
            @if($detail['image_url'])
                <img src="{{ $detail['image_url'] }}" alt="" class="max-h-48 object-contain">
            @else
                <span class="text-sm text-gray-400">Group image</span>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 bg-white lg:col-span-2">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-2xl font-bold text-gray-900">{{ $detail['uses_placeholder'] ? $detail['label'] : $detail['product_name'] }}</h2>
                <p class="mt-1 font-mono text-sm text-gray-500">Parent group · style key {{ $detail['label'] }}</p>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-gray-500">Style title (was “Product name”)</p>
                    <p class="text-lg text-gray-900">{{ $detail['uses_placeholder'] ? '— (lists use TYPE-parentMaster fallback)' : $detail['product_name'] }}</p>
                </div>
                <button type="button" @click="parentEditOpen = !parentEditOpen" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-800">
                    Edit style defaults
                </button>
                <div x-show="parentEditOpen" x-cloak class="rounded-lg border border-gray-100 bg-gray-50 p-4 text-sm text-gray-700">
                    Collapsible forms unchanged from live — labels only: <strong>Style title</strong>, brand/type, group pricing + cascade (mock).
                </div>
            </div>
        </div>
    </div>

    <h2 class="mb-3 text-xl font-bold text-gray-900">Colors &amp; sizes</h2>
    <p class="mb-3 text-sm text-gray-600">Live page keeps warehouse tables here; mock adds <strong>effective title</strong> hint per color header.</p>
    @include('items.mock.partials.colorway-catalog-table', [
        'colorwayCatalogRows' => $colorwayCatalogRows,
        'canEditGroup' => $canEditGroup,
    ])
</div>
@endsection
