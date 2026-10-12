@extends('layouts.app')

@section('title', 'Catalog UI mock')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Items', 'href' => route('items.index')],
    ['title' => 'Catalog UI mock', 'href' => '#'],
];
@endphp

<div class="p-4 sm:p-6">
    @include('items.mock.partials.mock-banner', ['liveUrl' => route('items.group')])

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Item catalog — proposed UX (mock)</h1>
        <p class="mt-2 max-w-3xl text-sm text-gray-600">
            Three levels: <strong>Style (parent)</strong> default title and group pricing;
            <strong>Colorway</strong> optional title override, descriptions, image, matrix pricing;
            <strong>SKU</strong> per-size price, restock, optional description override.
            Identity (pcode, SKU code) stays separate from marketing titles.
        </p>
    </div>

    <h2 class="mb-4 text-lg font-semibold text-gray-900">Title resolution scenarios</h2>
    <div class="mb-10 grid grid-cols-1 gap-6 lg:grid-cols-3">
        @foreach($scenarios as $scenario)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" data-testid="mock-scenario-{{ $scenario['key'] }}">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ $scenario['title_source_label'] }}</p>
            <h3 class="mt-1 text-base font-bold text-gray-900">{{ $scenario['heading'] }}</h3>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-2">
                    <dt class="text-gray-500">Parent title</dt>
                    <dd class="font-medium text-gray-900">{{ $scenario['parent_title'] !== '' ? $scenario['parent_title'] : '—' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-gray-500">Colorway title</dt>
                    <dd class="font-medium text-gray-900">{{ $scenario['colorway_title'] !== '' ? $scenario['colorway_title'] : '(empty = inherit)' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-gray-500">Style code</dt>
                    <dd class="font-mono text-gray-800">{{ $scenario['style_code'] }}</dd>
                </div>
            </dl>
            <p class="mt-4 rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs text-gray-800">
                List / invoice: {{ $scenario['preview'] }}
            </p>
            <p class="mt-3 text-xs text-gray-600">{{ $scenario['helper'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-6">
        <h2 class="text-lg font-semibold text-indigo-950">Try with real data</h2>
        <p class="mt-2 text-sm text-indigo-900">
            Open a parent group in Aria, then append <span class="font-mono">/mock-hub</span> to the URL
            (or use the link on the live parent page). Example path:
            <span class="font-mono text-indigo-800">/items-group/parent/{id}/mock-hub</span>
        </p>
        <a href="{{ route('items.group') }}"
           class="mt-4 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
           data-testid="mock-goto-group-list">
            Go to group list
        </a>
    </div>
</div>
@endsection
