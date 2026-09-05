@extends('layouts.app')

@section('title', 'Edit Setting')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'System Settings', 'href' => route('system-settings.index')],
    ['title' => 'Edit Setting', 'href' => route('system-settings.edit', $setting->id)],
];
$type = $definition['type'] ?? 'text';
$rawValue = is_array($setting->value) || is_object($setting->value) ? json_encode($setting->value) : $setting->value;
$currentValue = old('value', $rawValue);
$selectedWarehouseIds = old('warehouse_ids', is_array($setting->value) ? $setting->value : []);
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Edit Setting</h2>
        <p class="mt-0.5 text-sm text-gray-500">Update value for <span class="font-medium">{{ $setting->name }}</span>. The slug cannot be changed.</p>
    </div>

    <div class="max-w-2xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('system-settings.update', $setting->id) }}" class="space-y-4 p-6">
            @csrf
            @method('PUT')

            <div class="opacity-70">
                <label class="mb-1 block text-sm font-medium text-gray-700">Slug (System Key)</label>
                <div class="rounded border border-gray-200 bg-gray-50 p-2 font-mono text-sm text-gray-600">{{ $setting->slug }}</div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Group / Category</label>
                <div class="rounded border border-gray-200 bg-gray-50 p-2 text-sm text-gray-700">{{ $setting->group }}</div>
                <input type="hidden" name="group" value="{{ old('group', $setting->group) }}">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Name</label>
                <div class="rounded border border-gray-200 bg-gray-50 p-2 text-sm text-gray-700">{{ $setting->name }}</div>
                <input type="hidden" name="name" value="{{ old('name', $setting->name) }}">
            </div>

            @if(!empty($definition['hint']))
                <p class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm text-blue-800">{{ $definition['hint'] }}</p>
            @endif

            @if($type === 'account')
                <div x-data="asyncCombobox({
                        endpoint: @js($lookupUrls['account']),
                        placeholder: 'Select account...',
                        initial: @js($addrbookInitial),
                    })" x-init="init()" class="relative">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Value (Select Account)</label>
                    <input type="hidden" name="value" :value="selected ? selected.id : ''">
                    <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)"
                           :placeholder="placeholder" autocomplete="off"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    <div x-show="open" @click.away="open = false" x-cloak class="combobox-options" x-ref="optionsList">
                        <template x-for="(item, idx) in items" :key="item.id">
                            <div class="combobox-option" :class="{ 'active': idx === activeIndex }" @click="selectItem(item)" @mouseenter="activeIndex = idx">
                                <span x-text="item.name"></span>
                            </div>
                        </template>
                        <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400">No results</div>
                    </div>
                    @error('value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @elseif($type === 'addrbook_supplier')
                <div x-data="asyncCombobox({
                        endpoint: @js($lookupUrls['supplier']),
                        placeholder: 'Select supplier...',
                        initial: @js($addrbookInitial),
                    })" x-init="init()" class="relative">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Value (Select Supplier)</label>
                    <input type="hidden" name="value" :value="selected ? selected.id : ''">
                    <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)"
                           :placeholder="placeholder" autocomplete="off"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    <div x-show="open" @click.away="open = false" x-cloak class="combobox-options" x-ref="optionsList">
                        <template x-for="(item, idx) in items" :key="item.id">
                            <div class="combobox-option" :class="{ 'active': idx === activeIndex }" @click="selectItem(item)" @mouseenter="activeIndex = idx">
                                <span x-text="item.name"></span>
                            </div>
                        </template>
                        <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400">No results</div>
                    </div>
                    @error('value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @elseif($type === 'addrbook_warehouse')
                <div x-data="asyncCombobox({
                        endpoint: @js($lookupUrls['warehouse']),
                        placeholder: 'Select warehouse...',
                        initial: @js($addrbookInitial),
                    })" x-init="init()" class="relative">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Value (Select Warehouse)</label>
                    <input type="hidden" name="value" :value="selected ? selected.id : ''">
                    <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)"
                           :placeholder="placeholder" autocomplete="off"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    <div x-show="open" @click.away="open = false" x-cloak class="combobox-options" x-ref="optionsList">
                        <template x-for="(item, idx) in items" :key="item.id">
                            <div class="combobox-option" :class="{ 'active': idx === activeIndex }" @click="selectItem(item)" @mouseenter="activeIndex = idx">
                                <span x-text="item.name"></span>
                            </div>
                        </template>
                        <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400">No results</div>
                    </div>
                    @error('value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @elseif($type === 'warehouse_ids')
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Stock display warehouses</label>
                    <div class="max-h-56 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3">
                        @forelse($warehouses as $warehouse)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="warehouse_ids[]" value="{{ $warehouse->id }}"
                                       @checked(in_array($warehouse->id, $selectedWarehouseIds, true))
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span>{{ $warehouse->name }}</span>
                                <span class="text-xs text-gray-400">#{{ $warehouse->id }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500">No warehouse contacts found.</p>
                        @endforelse
                    </div>
                    @error('value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('warehouse_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @elseif($type === 'number')
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Value</label>
                    <input type="number" name="value" value="{{ $currentValue }}"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    @error('value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @elseif($type === 'boolean')
                <div>
                    <label for="setting-boolean-value" class="mb-1 block text-sm font-medium text-gray-700">Value</label>
                    <select id="setting-boolean-value" name="value"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                        <option value="1" @selected(filter_var($currentValue, FILTER_VALIDATE_BOOLEAN))>Included — prices include PPN</option>
                        <option value="0" @selected(! filter_var($currentValue, FILTER_VALIDATE_BOOLEAN))>Excluded — PPN added on top</option>
                    </select>
                    @error('value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @else
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Value</label>
                    <textarea name="value" rows="4"
                              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">{{ $currentValue }}</textarea>
                    @error('value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('system-settings.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
