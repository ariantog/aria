@extends('layouts.app')

@section('title', 'Create Service')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => route('items.index')],
    ['title' => 'Services', 'href' => route('services.index')],
    ['title' => 'Create', 'href' => '#'],
];
@endphp

<div class="mx-auto max-w-3xl p-4">
    <h2 class="mb-4 text-2xl font-bold text-gray-900">Create Service</h2>
    @if($errors->has('message'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $errors->first('message') }}</div>
    @endif
    <form method="POST" action="{{ route('services.store') }}" class="space-y-4">
        @csrf
        @include('services.partials.form', ['defaults' => $defaults])
        <div class="flex justify-end gap-2">
            <a href="{{ route('services.index') }}" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">Cancel</a>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save</button>
        </div>
    </form>
</div>
@endsection
