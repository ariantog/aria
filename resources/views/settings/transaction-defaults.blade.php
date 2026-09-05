@extends('layouts.app')
@section('title', 'Transaction defaults')

@push('settings-content')
    <div class="space-y-6">
        <header>
            <h2 class="text-base font-medium text-gray-900">Transaction defaults</h2>
            <p class="text-sm text-gray-500">Pre-fill sender, receiver, warehouse, and bank accounts when creating transactions. Only contacts in your location are available.</p>
        </header>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('transaction-defaults.update') }}" class="space-y-8">
            @csrf
            @method('PUT')

            @foreach($groups as $groupName => $fields)
                <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">{{ $groupName }}</h3>

                    @foreach($fields as $fieldConfig)
                        <div x-data="asyncCombobox({
                                endpoint: @js($lookupUrls[$fieldConfig['lookupType']]),
                                placeholder: 'Leave empty for no default…',
                                initial: @js($fieldConfig['initial']),
                            })" x-init="init()" class="relative">
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ $fieldConfig['definition']['label'] }}</label>
                            <p class="mb-2 text-xs text-gray-500">{{ $fieldConfig['definition']['hint'] }}</p>
                            <input type="hidden" name="{{ $fieldConfig['field'] }}" :value="selected ? selected.id : ''">
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
                            @error($fieldConfig['field'])<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </section>
            @endforeach

            <div class="flex items-center gap-4">
                <button type="submit"
                        class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Save defaults
                </button>
            </div>
        </form>
    </div>
@endpush

@section('content')
    @php
        $breadcrumbs = [
            ['title' => 'Transaction defaults', 'href' => route('transaction-defaults.edit')],
        ];
    @endphp

    @include('settings.partials.nav')
@endsection
