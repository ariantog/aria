@extends('layouts.app')

@section('title', 'Edit Service')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => route('items.index')],
    ['title' => 'Services', 'href' => route('services.index')],
    ['title' => 'Edit', 'href' => '#'],
];
@endphp

<div class="mx-auto max-w-3xl p-4">
    <h2 class="mb-4 text-2xl font-bold text-gray-900">Edit Service</h2>
    <p class="mb-4 text-sm text-gray-500 font-mono">{{ $item->code }}</p>
    @if($errors->has('message'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $errors->first('message') }}</div>
    @endif
    <form method="POST" action="{{ route('services.update', $item) }}" class="space-y-4">
        @csrf
        @method('PUT')
        @include('services.partials.form', ['item' => $item])
        <div class="flex justify-end gap-2">
            <a href="{{ route('services.show', $item) }}" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">Cancel</a>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Update</button>
        </div>
    </form>
</div>
@endsection
