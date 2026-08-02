@extends('layouts.app')

@section('title', 'Add Setting')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'System Settings', 'href' => route('system-settings.index')],
    ['title' => 'Add Setting', 'href' => route('system-settings.create')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Add New Setting</h2>
        <p class="mt-0.5 text-sm text-gray-500">Create a new configuration parameter.</p>
    </div>

    <div class="max-w-2xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('system-settings.store') }}" class="p-6">
            @csrf
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Group / Category <span class="text-red-500">*</span></label>
                    <input type="text" name="group" value="{{ old('group') }}" required placeholder="e.g. Accounting, System, Inventory"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    @error('group')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Sales Tax Rate"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Slug (Unique Identifier) <span class="text-red-500">*</span></label>
                    <input type="text" name="slug" value="{{ old('slug') }}" required placeholder="e.g. sales_tax_rate"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    @error('slug')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Value</label>
                    <input type="text" name="value" value="{{ old('value') }}" placeholder="Enter the setting value..."
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    @error('value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('system-settings.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Create Setting</button>
            </div>
        </form>
    </div>
</div>
@endsection
