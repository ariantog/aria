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
        <h1 class="text-2xl font-bold text-gray-900">Compare catalog UI mockups</h1>
        <p class="mt-2 max-w-3xl text-sm text-gray-600">
            All layouts are <strong>read-only</strong>. Pick a group from
            <a href="{{ route('items.group') }}" class="font-medium text-indigo-600 underline">Groups</a>,
            open the live parent page, then use the indigo preview banner or the URLs below.
            Live pages are unchanged.
        </p>
    </div>

    <div class="mb-10 grid grid-cols-1 gap-8 lg:grid-cols-3">
        <section class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-gray-900">Parent / style</h2>
            <ul class="mt-3 space-y-3 text-sm">
                @foreach($parentVariants as $v)
                <li class="rounded-lg border border-gray-100 bg-gray-50/80 p-3">
                    <p class="font-medium text-gray-900">{{ $v['label'] }}</p>
                    <p class="mt-1 text-gray-600">{{ $v['summary'] }}</p>
                    <p class="mt-2 font-mono text-xs text-gray-500">
                        @if($v['key'] === 'a')
                            …/items-group/parent/<strong>{id}</strong>/mock-hub
                        @else
                            …/mock-hub/{{ $v['key'] }}
                        @endif
                    </p>
                </li>
                @endforeach
            </ul>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-gray-900">Colorway</h2>
            <ul class="mt-3 space-y-3 text-sm">
                @foreach($colorwayVariants as $v)
                <li class="rounded-lg border border-gray-100 bg-gray-50/80 p-3">
                    <p class="font-medium text-gray-900">{{ $v['label'] }}</p>
                    <p class="mt-1 text-gray-600">{{ $v['summary'] }}</p>
                    <p class="mt-2 font-mono text-xs text-gray-500">
                        @if($v['key'] === 'a')
                            …/colorway/<strong>{groupId}</strong>/mock-edit
                        @else
                            …/mock-edit/{{ $v['key'] }}
                        @endif
                    </p>
                </li>
                @endforeach
            </ul>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-gray-900">SKU</h2>
            <ul class="mt-3 space-y-3 text-sm">
                @foreach($skuVariants as $v)
                <li class="rounded-lg border border-gray-100 bg-gray-50/80 p-3">
                    <p class="font-medium text-gray-900">{{ $v['label'] }}</p>
                    <p class="mt-1 text-gray-600">{{ $v['summary'] }}</p>
                    <p class="mt-2 font-mono text-xs text-gray-500">
                        @if($v['key'] === 'a')
                            …/items/<strong>{itemId}</strong>/mock-sku
                        @else
                            …/mock-sku/{{ $v['key'] }}
                        @endif
                    </p>
                </li>
                @endforeach
            </ul>
        </section>
    </div>

    <h2 class="mb-4 text-lg font-semibold text-gray-900">Title resolution (reference)</h2>
    <div class="mb-10 grid grid-cols-1 gap-6 lg:grid-cols-3">
        @foreach($scenarios as $scenario)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" data-testid="mock-scenario-{{ $scenario['key'] }}">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ $scenario['title_source_label'] }}</p>
            <h3 class="mt-1 text-base font-bold text-gray-900">{{ $scenario['heading'] }}</h3>
            <p class="mt-4 rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs text-gray-800">List: {{ $scenario['preview'] }}</p>
            <p class="mt-3 text-xs text-gray-600">{{ $scenario['helper'] }}</p>
        </div>
        @endforeach
    </div>

    <p class="text-sm text-gray-500">
        Tip: on any parent mock (A–D), use the <strong>Compare mock layouts</strong> bar to switch variants without changing the group id.
    </p>
</div>
@endsection
