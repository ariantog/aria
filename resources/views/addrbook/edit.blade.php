@extends('layouts.app')

@section('title', 'Edit ' . $addrbook->name)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Address Book', 'href' => route('addrbook.index')],
    ['title' => $addrbook->name, 'href' => '/' . $addrbook->type_slug . '/' . $addrbook->id . '/edit'],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <form method="POST" action="{{ route('addrbook.update', $addrbook->id) }}" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        @method('PUT')

        {{-- Header --}}
        <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h1 class="mb-1 text-2xl font-bold tracking-tight text-gray-900">Edit Entry</h1>
                <p class="text-sm text-gray-500">Update details for {{ $addrbook->name }}.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('addrbook.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" :disabled="submitting"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                    <span x-show="!submitting">Save Changes</span>
                    <span x-show="submitting" x-cloak>Saving...</span>
                </button>
            </div>
        </div>

        @include('addrbook.partials.form', ['mode' => 'edit'])
    </form>
</div>
@endsection
