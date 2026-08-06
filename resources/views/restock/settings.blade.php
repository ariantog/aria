@extends('layouts.app')

@section('title', 'Restock Settings')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
    ['title' => 'Settings', 'href' => route('restock.settings.edit')],
];
$supplierInitial = $settings['supplier']
    ? ['id' => $settings['supplier']->id, 'name' => $settings['supplier']->name]
    : null;
$receiverInitial = $settings['receiver']
    ? ['id' => $settings['receiver']->id, 'name' => $settings['receiver']->name]
    : null;
@endphp

<div class="flex flex-col gap-4 p-4">
    @include('restock.partials.type-tabs', [
        'typeTags' => $typeTags,
        'activeTypeTag' => null,
    ])

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Restock Settings</h1>
            <p class="text-sm text-gray-500">Defaults for receive into warehouse and stock display on the sheet grid.</p>
        </div>
        <a href="{{ route('restock.index') }}"
           class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Back to restock
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="max-w-2xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('restock.settings.update') }}" class="space-y-5 p-6">
            @csrf
            @method('PUT')

            <div x-data="asyncCombobox({
                    endpoint: @js($supplierLookupUrl),
                    hiddenField: 'default_supplier_id',
                    placeholder: 'Select supplier...',
                    initial: @js($supplierInitial),
                })" x-init="init(); if (selected) { query = selected.name; }" class="relative">
                <label class="mb-1 block text-sm font-medium text-gray-700">Default supplier <span class="text-red-500">*</span></label>
                <p class="mb-2 text-xs text-gray-500">Sender on Buy transactions when receiving shipped stock.</p>
                <input type="hidden" name="default_supplier_id" id="default_supplier_id" value="{{ old('default_supplier_id', $settings['default_supplier_id']) }}">
                <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)"
                       :placeholder="placeholder" autocomplete="off" :disabled="!@json($canEdit)"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 disabled:bg-gray-50">
                <div x-show="open" @click.away="open = false" x-cloak class="combobox-options" x-ref="optionsList">
                    <template x-for="(item, idx) in items" :key="item.id">
                        <div class="combobox-option" :class="{ 'active': idx === activeIndex }" @click="selectItem(item)" @mouseenter="activeIndex = idx">
                            <span x-text="item.name"></span>
                        </div>
                    </template>
                    <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400">No results</div>
                </div>
                @error('default_supplier_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div x-data="asyncCombobox({
                    endpoint: @js($receiverLookupUrl),
                    hiddenField: 'default_receiver_id',
                    placeholder: 'Select warehouse...',
                    initial: @js($receiverInitial),
                })" x-init="init(); if (selected) { query = selected.name; }" class="relative">
                <label class="mb-1 block text-sm font-medium text-gray-700">Default receiver warehouse <span class="text-red-500">*</span></label>
                <p class="mb-2 text-xs text-gray-500">Warehouse that receives stock on Buy transactions from receive.</p>
                <input type="hidden" name="default_receiver_id" id="default_receiver_id" value="{{ old('default_receiver_id', $settings['default_receiver_id']) }}">
                <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)"
                       :placeholder="placeholder" autocomplete="off" :disabled="!@json($canEdit)"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 disabled:bg-gray-50">
                <div x-show="open" @click.away="open = false" x-cloak class="combobox-options" x-ref="optionsList">
                    <template x-for="(item, idx) in items" :key="item.id">
                        <div class="combobox-option" :class="{ 'active': idx === activeIndex }" @click="selectItem(item)" @mouseenter="activeIndex = idx">
                            <span x-text="item.name"></span>
                        </div>
                    </template>
                    <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400">No results</div>
                </div>
                @error('default_receiver_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Stock display warehouses</label>
                <p class="mb-2 text-xs text-gray-500">Hover stock on the sheet grid sums qty from these warehouses only. Leave all unchecked to sum every warehouse.</p>
                <div class="max-h-56 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3">
                    @forelse($settings['warehouses'] as $warehouse)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="default_warehouse_ids[]" value="{{ $warehouse->id }}"
                                   @checked(in_array($warehouse->id, old('default_warehouse_ids', $settings['default_warehouse_ids']), true))
                                   @disabled(!$canEdit)
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50">
                            <span>{{ $warehouse->name }}</span>
                            <span class="text-xs text-gray-400">#{{ $warehouse->id }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-500">No warehouse contacts found.</p>
                    @endforelse
                </div>
                @error('default_warehouse_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            @if($canEdit)
            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Save settings
                </button>
            </div>
            @endif
        </form>
    </div>
</div>
@endsection
