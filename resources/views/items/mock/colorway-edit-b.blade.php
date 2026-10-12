@extends('layouts.app')

@section('title', 'Mock B colorway: ' . ($color['code'] ?? ''))

@section('content')
@php
$mockHubUrl = route('items.catalog-mock.parent-hub', $parentGroupId);
$liveColorwayUrl = route('items.colorway-edit', $group);
@endphp

<div class="p-4 sm:p-6">
    @include('items.mock.partials.mock-banner', ['liveUrl' => $liveColorwayUrl])
    @include('items.mock.partials.mock-variant-nav', [
        'mockSurface' => 'colorway',
        'colorwayGroupId' => $group->id,
        'activeVariant' => 'b',
    ])

    <p class="mb-4 text-sm text-gray-600"><strong>Variant B:</strong> production users set prices per size first — matrix on top, marketing copy below.</p>

    <fieldset disabled class="space-y-8">
        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="font-semibold text-gray-900">Size matrix (price · restock)</h3>
            <table class="mt-3 min-w-full text-sm">
                <thead class="text-xs uppercase text-gray-500"><tr><th class="py-2 text-left">Size</th><th class="py-2 text-left">SKU</th><th class="py-2 text-right">Price</th></tr></thead>
                <tbody>
                    @foreach($previewRows as $row)
                    <tr class="border-t border-gray-100"><td class="py-2">{{ $row['size_code'] }}</td><td class="font-mono text-xs">{{ $row['code'] }}</td><td class="py-2 text-right">(mock)</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <h3 class="font-semibold text-gray-900">Identity</h3>
                <p class="mt-2 font-mono text-sm">{{ $sample->pcode }} · {{ $color['code'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <h3 class="font-semibold text-gray-900">Catalog (this color)</h3>
                <label class="mt-2 block text-sm">Colorway title (optional)</label>
                <input type="text" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" value="{{ $productTitle }}">
            </div>
        </div>
    </fieldset>
</div>
@endsection
