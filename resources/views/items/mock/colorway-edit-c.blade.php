@extends('layouts.app')

@section('title', 'Mock C colorway: ' . ($color['code'] ?? ''))

@section('content')
@php
$liveColorwayUrl = route('items.colorway-edit', $group);
@endphp

<div class="p-4 sm:p-6" x-data="{ fieldScope: 'colorway' }">
    @include('items.mock.partials.mock-banner', ['liveUrl' => $liveColorwayUrl])
    @include('items.mock.partials.mock-variant-nav', [
        'mockSurface' => 'colorway',
        'colorwayGroupId' => $group->id,
        'activeVariant' => 'c',
    ])

    <p class="mb-4 text-sm text-gray-600"><strong>Variant C:</strong> one form; each field shows which level it writes (mock scope chips).</p>

    <fieldset disabled class="max-w-2xl space-y-6 rounded-xl border border-gray-200 bg-white p-5">
        @foreach([
            ['label' => 'Colorway title (optional)', 'scope' => 'colorway'],
            ['label' => 'Description', 'scope' => 'colorway'],
            ['label' => 'Sell price (size M)', 'scope' => 'size'],
            ['label' => 'Restock threshold (size M)', 'scope' => 'size'],
        ] as $field)
        <div>
            <div class="mb-1 flex items-center justify-between gap-2">
                <label class="text-sm font-medium text-gray-800">{{ $field['label'] }}</label>
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-600">{{ $field['scope'] }}</span>
            </div>
            <input type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>
        @endforeach
        <button type="button" class="rounded-lg bg-gray-400 px-4 py-2 text-sm text-white">Save (mock)</button>
    </fieldset>
</div>
@endsection
