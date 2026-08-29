@extends('layouts.app')

@section('title', 'Add Asset Tetap')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Asset Tetap', 'href' => route('assettetap.index')],
    ['title' => 'Add', 'href' => route('assettetap.create')],
];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex items-center gap-3">
        <a href="{{ route('assettetap.index') }}" class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50">
            <svg class="h-4 w-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Add Asset Tetap</h2>
            <p class="mt-0.5 text-sm text-gray-500">Daftarkan asset. Bahan habis pakai tidak dilacak — anggap terpakai di bulan beli.</p>
        </div>
    </div>

    @if($errors->any())
    <div class="max-w-3xl rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ route('assettetap.store') }}" class="max-w-3xl space-y-5" data-testid="assettetap-create-form">
        @csrf
        @include('assettetap.partials.form-fields', ['item' => null, 'register' => null])
        <div class="flex gap-2">
            <button type="submit" data-testid="assettetap-save" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save</button>
            <a href="{{ route('assettetap.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</a>
        </div>
    </form>
</div>
@endsection
