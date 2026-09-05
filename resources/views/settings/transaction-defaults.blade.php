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

            <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">Tax</h3>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <label for="default-ppn-included" class="block text-sm font-medium text-gray-700">Default PPN mode</label>
                        <p class="mt-1 text-xs text-gray-500">When the counterparty is taxable, new Buy / Sell / Return / Return Supplier forms start with PPN included or excluded.</p>
                    </div>
                    <label class="inline-flex shrink-0 cursor-pointer items-center gap-2">
                        <span class="text-xs font-medium text-gray-600">{{ $ppnIncludedDefault ? 'Included' : 'Excluded' }}</span>
                        <span class="relative inline-flex h-6 w-11 shrink-0 items-center">
                            <input type="hidden" name="default_ppn_included" value="0">
                            <input type="checkbox" id="default-ppn-included" name="default_ppn_included" value="1"
                                   @checked($ppnIncludedDefault)
                                   class="peer sr-only">
                            <span class="absolute inset-0 rounded-full bg-gray-300 peer-checked:bg-blue-600"></span>
                            <span class="absolute left-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                </div>
            </section>

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
