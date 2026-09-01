@extends('layouts.app')

@section('title', $isAsset ? 'Edit Asset' : 'Edit Item')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => $isAsset ? 'Asset Lancar' : 'Items', 'href' => $isAsset ? route('assetlancar.index') : route('items.index')],
    ['title' => 'Edit', 'href' => '#'],
];
$actionUrl = $isAsset ? route('assetlancar.update', $item->id) : route('items.update', $item->id);

$curType  = optional($item->tags->firstWhere('type', \App\Models\Tag::TYPE_TYPE))->id;
$curJahit = optional($item->tags->firstWhere('type', \App\Models\Tag::TYPE_JAHIT))->id;
$curWarna = optional($item->tags->firstWhere('type', \App\Models\Tag::TYPE_WARNA))->id;
$curSizes = $item->tags->where('type', \App\Models\Tag::TYPE_SIZE)->pluck('id')->all();

$itemType = $item->type->value;
$productTitle = $productTitle ?? '';
$legacyAssetProductName = '';
if ($isAsset && ! $item->group) {
    $legacyAssetProductName = str_contains($item->name, ' - ')
        ? trim(explode(' - ', $item->name, 2)[0])
        : $item->name;
}
$formItem = [
    'pcode' => old('pcode', $item->pcode),
    'product_name' => old('product_name', $productTitle !== '' && strtoupper($productTitle) !== strtoupper((string) $item->pcode)
        ? $productTitle
        : ($legacyAssetProductName ?: '')),
    'price' => old('price', $item->price),
    'cost' => old('cost', $item->cost),
    'description' => old('description', $item->description),
    'description2' => old('description2', $item->description2),
    'url' => old('url', optional($item->group)->url),
    'restock_urgent_threshold' => old('restock_urgent_threshold', $item->restock_urgent_threshold),
];
@endphp

<div class="flex flex-col gap-4 p-4" x-data="itemForm()" x-init="init()">
    <div class="mb-2">
        <h2 class="mb-1 text-3xl font-bold tracking-tight text-gray-900">{{ $isAsset ? 'Edit Asset' : 'Edit Item' }}</h2>
        <p class="text-gray-500">Update <span class="font-mono text-sm">{{ $item->code }}</span></p>
    </div>

    @include('items.partials.form-errors')

    <form method="POST" action="{{ $actionUrl }}" enctype="multipart/form-data" class="space-y-8">
        @csrf
        @method('PUT')
        <input type="hidden" name="type" value="{{ $itemType }}">

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                @include('items.partials.form-basic', ['formItem' => $formItem])
                @include('items.partials.form-details', ['formItem' => $formItem])
                @include('items.partials.form-preview')
            </div>
            <div class="space-y-6">
                @include('items.partials.form-attributes', [
                    'multiSize' => false,
                    'curType' => $curType,
                    'curJahit' => $curJahit,
                    'curWarna' => $curWarna,
                    'curSizes' => $curSizes,
                ])
                @include('items.partials.form-image', ['imageUrl' => $item->image_url])
            </div>
        </div>

        <div class="flex justify-end gap-4 border-t border-gray-200 pt-8">
            <button type="button" onclick="window.history.back()" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-900">Cancel</button>
            <button type="submit" class="min-w-[150px] rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Update {{ $isAsset ? 'Asset' : 'Item' }}</button>
        </div>
    </form>
</div>

@include('items.partials.form-scripts', ['multiSize' => false, 'isAsset' => $isAsset, 'formItem' => $formItem])
@endsection
