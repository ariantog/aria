@extends('layouts.app')

@php
    $isAsset = $itemType == 2;
    $formItem = [
        'pcode' => old('pcode'),
        'product_name' => old('product_name'),
        'price' => old('price'),
        'cost' => old('cost'),
        'description' => old('description'),
        'description2' => old('description2'),
        'url' => old('url'),
        'restock_urgent_threshold' => old('restock_urgent_threshold'),
    ];
@endphp
@section('title', $isAsset ? 'Create New Asset' : 'Create New Item')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => $isAsset ? 'Asset Lancar' : 'Items', 'href' => $isAsset ? route('assetlancar.index') : route('items.index')],
    ['title' => 'Create New', 'href' => '#'],
];
$actionUrl = $isAsset ? route('assetlancar.store') : route('items.store');
@endphp

<div class="flex flex-col gap-4 p-4" x-data="itemForm()" x-init="init()">
    <div class="mb-2">
        <h2 class="mb-1 text-3xl font-bold tracking-tight text-gray-900">{{ $isAsset ? 'Create New Asset' : 'Create New Item' }}</h2>
        <p class="text-gray-500">
            @if($isAsset)
                Add asset lancar variants — select multiple colors and sizes; each combination becomes one SKU.
            @else
                Add manufactured item sizes for one production code and color (pcode suffix).
            @endif
        </p>
    </div>

    @include('items.partials.form-errors')

    <form method="POST" action="{{ $actionUrl }}" enctype="multipart/form-data" class="space-y-8">
        @csrf
        <input type="hidden" name="type" value="{{ $itemType }}">

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                @include('items.partials.form-basic', ['formItem' => $formItem])
                @include('items.partials.form-details', ['formItem' => $formItem])
                @include('items.partials.form-preview')
            </div>
            <div class="space-y-6">
                @include('items.partials.form-attributes', ['multiSize' => true])
                @include('items.partials.form-image')
            </div>
        </div>

        <div class="flex justify-end gap-4 border-t border-gray-200 pt-8">
            <button type="button" onclick="window.history.back()" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-900">Cancel</button>
            <button type="submit" class="min-w-[150px] rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create {{ $isAsset ? 'Asset' : 'Item' }}</button>
        </div>
    </form>
</div>

@include('items.partials.form-scripts', ['multiSize' => true, 'isAsset' => $isAsset, 'formItem' => $formItem])
@endsection
