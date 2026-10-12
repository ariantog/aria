@extends('layouts.app')

@section('title', 'Mock colorway: ' . ($color['code'] ?? $group->name))

@section('content')
@php
$breadcrumbs = [
    ['title' => $isAsset ? 'Assets' : 'Items', 'href' => $isAsset ? route('assetlancar.index') : route('items.index')],
    ['title' => 'Groups', 'href' => route('items.group')],
    ['title' => $sample->pcode, 'href' => route('items.catalog-mock.parent-hub', $parentGroupId)],
    ['title' => 'Mock colorway', 'href' => '#'],
];
$liveColorwayUrl = route('items.colorway-edit', $group);
$mockHubUrl = route('items.catalog-mock.parent-hub', $parentGroupId);
@endphp

<div class="p-4 sm:p-6" x-data="{
    colorwayTitle: @js($productTitle),
    parentTitle: @js($parentProductName),
    usesPlaceholder: @js($usesPlaceholder),
    warnaCode: @js($color['code'] ?? ''),
    previewRows: @js($previewRows),
    effectiveBare() {
        const t = (this.colorwayTitle || '').trim();
        if (t !== '') return t.toUpperCase();
        if ((this.parentTitle || '').trim() !== '') return this.parentTitle.toUpperCase();
        return @js($isAsset
            ? strtoupper(trim((string) $sample->pcode))
            : app(\App\Services\Items\ItemIdentityBuilder::class)->manufacturedCatalogTitleFallback($sample));
    },
    previewName(sizeCode) {
        const bare = this.effectiveBare();
        const w = this.warnaCode;
        const parts = [bare];
        if (w) parts.push(w);
        if (sizeCode && sizeCode !== '—') parts.push(sizeCode);
        return parts.join(' - ');
    }
}">
    @include('items.mock.partials.mock-banner', ['liveUrl' => $liveColorwayUrl])
    @include('items.mock.partials.mock-variant-nav', [
        'mockSurface' => 'colorway',
        'colorwayGroupId' => $group->id,
        'activeVariant' => 'a',
    ])

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ $mockHubUrl }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Mock product hub
        </a>
        <a href="{{ $liveColorwayUrl }}" class="text-sm text-gray-500 underline hover:text-gray-800">Live colorway editor</a>
    </div>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="mock-colorway-identity">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">Identity</h3>
                    <p class="text-sm text-gray-500">Technical codes — not marketing titles.</p>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-500">Style code (pcode)</p>
                        <p class="font-mono text-sm text-gray-900">{{ $sample->pcode }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-500">Color (warna)</p>
                        <p class="text-sm text-gray-900">{{ $color['code'] }} — {{ $color['name'] }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs font-semibold uppercase text-gray-500">Style title (from parent)</p>
                        <p class="text-sm text-gray-900">{{ $parentProductName !== '' ? $parentProductName : '— not set —' }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            Edit on <a href="{{ $mockHubUrl }}" class="text-indigo-600 underline">mock hub → Style defaults</a>.
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="mock-colorway-catalog">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">Catalog (this color only)</h3>
                    <p class="text-sm text-gray-500">Applies to every size in this colorway.</p>
                </div>
                <fieldset disabled class="space-y-4 p-5">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Colorway title (optional)</label>
                        <input type="text"
                               x-model="colorwayTitle"
                               :placeholder="parentTitle !== '' ? ('Inherits: ' + parentTitle) : 'Inherits style code until you type a title'"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                               data-testid="mock-colorway-title-input">
                        <p class="mt-1 text-xs text-gray-500">Leave empty to inherit parent style title (or style code if parent is empty).</p>
                    </div>
                    <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-xs text-gray-700">
                        <p><span class="font-semibold">Effective bare title:</span> <span class="font-mono" x-text="effectiveBare()"></span></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                        <textarea rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="(mock)"></textarea>
                    </div>
                    <button type="button" class="rounded-lg bg-gray-400 px-4 py-2 text-sm font-medium text-white cursor-not-allowed">
                        Save colorway (mock)
                    </button>
                </fieldset>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-5" data-testid="mock-colorway-preview">
                <h3 class="text-sm font-semibold text-indigo-950">Live preview</h3>
                <p class="mt-1 text-xs text-indigo-900">How list / invoice names would build:</p>
                <ul class="mt-3 space-y-2">
                    <template x-for="row in previewRows" :key="row.code">
                        <li class="rounded-lg border border-indigo-100 bg-white px-3 py-2">
                            <p class="font-mono text-xs text-gray-500" x-text="row.code"></p>
                            <p class="text-sm font-medium text-gray-900" x-text="previewName(row.size_code)"></p>
                        </li>
                    </template>
                </ul>
            </div>

            @include('items.partials.form-colorway-title-hints', ['titleForm' => $titleForm])
        </div>
    </div>
</div>
@endsection
