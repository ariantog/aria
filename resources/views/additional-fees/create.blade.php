@extends('layouts.app')

@section('title', 'Create Additional Fee')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Additional Fees', 'href' => route('additional-fees.index')],
    ['title' => 'Create Fee', 'href' => route('additional-fees.create')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Create Additional Fee</h2>
    </div>

    <div class="max-w-lg overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('additional-fees.store') }}" class="space-y-4 p-6">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Shipping Fee"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Type <span class="text-red-500">*</span></label>
                <select name="type" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    <option value="nominal" @selected(old('type', 'nominal') === 'nominal')>Nominal</option>
                    <option value="percent" @selected(old('type') === 'percent')>Percent</option>
                </select>
                @error('type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Value <span class="text-red-500">*</span></label>
                <input type="number" step="any" min="0" name="value" value="{{ old('value') }}" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                @error('value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('additional-fees.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Create Fee</button>
            </div>
        </form>
    </div>
</div>
@endsection
