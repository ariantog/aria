@extends('layouts.app')

@section('title', 'Invoice Settings')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'System Settings', 'href' => route('system-settings.index')],
    ['title' => 'Invoice', 'href' => route('invoice-settings.edit')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Invoice Settings</h2>
        <p class="mt-0.5 text-sm text-gray-500">Logo, address, and phone shown on transaction invoice PDFs.</p>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="max-w-2xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('invoice-settings.update') }}" enctype="multipart/form-data" class="p-6 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="company_name" class="mb-1 block text-sm font-medium text-gray-700">Company Name</label>
                <input type="text" id="company_name" name="company_name" value="{{ old('company_name', $branding['company_name']) }}" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                @error('company_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="address" class="mb-1 block text-sm font-medium text-gray-700">Address</label>
                <textarea id="address" name="address" rows="4" required
                          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ old('address', $branding['address']) }}</textarea>
                @error('address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="phone" class="mb-1 block text-sm font-medium text-gray-700">Phone</label>
                <input type="text" id="phone" name="phone" value="{{ old('phone', $branding['phone']) }}" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="logo" class="mb-1 block text-sm font-medium text-gray-700">Logo</label>
                @if($branding['logo_url'])
                    <div class="mb-3 flex items-center gap-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <img src="{{ $branding['logo_url'] }}" alt="Current logo" class="h-16 w-auto object-contain">
                        <span class="text-sm text-gray-500">Current logo</span>
                    </div>
                @endif
                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"
                       class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100">
                <p class="mt-1 text-xs text-gray-500">PNG, JPG, or WebP. Max 2 MB. Leave empty to keep the current logo.</p>
                @error('logo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('system-settings.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Back</a>
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection
