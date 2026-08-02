@extends('layouts.app')

@section('title', 'Upload Restock Excel')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
    ['title' => 'Upload Excel', 'href' => route('restock.uploadExcel')],
];
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="mb-4">
        <h1 class="mb-1 text-3xl font-bold tracking-tight text-zinc-900">Upload Restock Data</h1>
        <p class="text-zinc-500">Bulk import restock records from an Excel or CSV file.</p>
    </div>

    <div class="max-w-xl">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="mb-6 flex items-start gap-3 rounded-lg bg-zinc-50 p-4 text-sm text-zinc-600">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p class="font-medium text-zinc-900">Excel Format Instructions:</p>
                    <ul class="mt-1 list-inside list-disc space-y-1">
                        <li>Column 1: Item ID or Code</li>
                        <li>Column 2: Quantity</li>
                        <li>Make sure the items already exist in the system.</li>
                    </ul>
                </div>
            </div>

            <form method="POST" action="{{ route('restock.importExcel') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <div class="space-y-2">
                    <label class="text-sm font-medium">Excel / CSV File</label>
                    <input type="file" name="file" accept=".xlsx,.csv" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    @error('file')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Import Type</label>
                        <select name="type" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="restocked" @selected(old('type','restocked')==='restocked')>Restocked (Initial)</option>
                            <option value="production" @selected(old('type')==='production')>Move to Production</option>
                            <option value="shipped" @selected(old('type')==='shipped')>Move to Shipped</option>
                            <option value="missing" @selected(old('type')==='missing')>Missing</option>
                        </select>
                        @error('type')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Reference Date</label>
                        <input type="date" name="date" value="{{ old('date', now()->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('date')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                </div>
                @error('import')<div class="whitespace-pre-wrap rounded-lg bg-red-50 p-3 text-sm text-red-600">{{ $message }}</div>@enderror
                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Start Import
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
