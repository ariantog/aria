@extends('layouts.app')

@section('title', 'Mock C (single scroll): ' . $detail['label'])

@section('content')
@php
$liveParentUrl = route('items.group-parent-detail', $detail['anchor_group_id']);
@endphp

<div class="p-4 sm:p-6" x-data="{ scope: 'style' }">
    @include('items.mock.partials.mock-banner', ['liveUrl' => $liveParentUrl])
    @include('items.mock.partials.mock-variant-nav', [
        'mockSurface' => 'parent',
        'groupId' => $detail['anchor_group_id'],
        'activeVariant' => 'c',
    ])

    <p class="mb-4 text-sm text-gray-600">
        <strong>Variant C:</strong> one scroll — scope chip chooses what you edit (style vs colorway). No route hopping; save per section (mock).
    </p>

    <div class="mb-3 flex flex-wrap gap-2">
        <button type="button" @click="scope = 'style'" :class="scope === 'style' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'" class="rounded-full px-3 py-1 text-xs font-semibold">Style (all colorways)</button>
        <button type="button" @click="scope = 'colorway'" :class="scope === 'colorway' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'" class="rounded-full px-3 py-1 text-xs font-semibold">One colorway</button>
    </div>

    <div class="space-y-8">
        <section class="rounded-xl border border-gray-200 bg-white p-5" x-show="scope === 'style'" x-cloak>
            <h2 class="text-lg font-semibold text-gray-900">Style title (default)</h2>
            <fieldset disabled class="mt-3 max-w-lg space-y-3">
                <input type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Style title for all colorways" value="{{ $detail['uses_placeholder'] ? '' : $detail['product_name'] }}">
                <label class="flex gap-2 text-sm"><input type="checkbox" class="rounded"> Replace all colorway titles</label>
                <button type="button" class="rounded-lg bg-gray-400 px-4 py-2 text-sm text-white">Save style section (mock)</button>
            </fieldset>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-gray-900">Colorways at a glance</h2>
            <div class="mt-3" x-show="scope === 'colorway'" x-cloak>
                <p class="mb-2 text-xs text-indigo-800">Scope = one colorway → row actions open inline editor (mock).</p>
            </div>
            @include('items.mock.partials.colorway-catalog-table', [
                'colorwayCatalogRows' => $colorwayCatalogRows,
                'canEditGroup' => $canEditGroup,
            ])
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5" x-show="scope === 'style'" x-cloak>
            <h2 class="text-lg font-semibold text-gray-900">Group pricing (parent)</h2>
            <p class="mt-1 text-sm text-gray-600">Same fields as live parent pricing + cascade checkbox (mock, disabled).</p>
        </section>
    </div>
</div>
@endsection
