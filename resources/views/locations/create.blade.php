@extends('layouts.app')

@section('title', 'Create Location')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Locations', 'href' => route('locations.index')],
    ['title' => 'Create Location', 'href' => route('locations.create')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Create New Location</h2>
        <p class="mt-0.5 text-sm text-gray-500">Add a new location to the system.</p>
    </div>

    <div class="max-w-lg overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('locations.store') }}" class="p-6">
            @csrf
            <div class="mb-6">
                <label class="mb-1 block text-sm font-medium text-gray-700">Location Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Main Warehouse"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end gap-3">
                <a href="{{ route('locations.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Create Location</button>
            </div>
        </form>
    </div>
</div>
@endsection
