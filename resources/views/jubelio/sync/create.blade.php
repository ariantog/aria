@extends('layouts.app')

@section('title', 'Create Jubelio Sync Mapping')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio Sync Mapping', 'href' => route('jubelio.sync.index')],
    ['title' => 'Create Mapping', 'href' => route('jubelio.sync.create')],
];
$warehouseRoute = route('transactions.lookup', ['type' => 'sell', 'role' => 'sender', 'addrbook_type' => $addrbookTypes['warehouse']]);
$customerRoute = route('transactions.lookup', ['type' => 'sell', 'role' => 'receiver', 'addrbook_type' => $addrbookTypes['customer']]);
$storesUrlTemplate = route('jubelio.sync.locationStores', ['locationId' => '__LOCATION__']);
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('jubelio.sync.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h1 class="text-2xl font-bold">Create Sync Mapping</h1>
    </div>

    <div class="max-w-2xl rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h3 class="text-sm font-semibold text-gray-900">Mapping Configuration</h3>
            <p class="mt-1 text-xs text-gray-500">Pilih lokasi Jubelio, lalu centang marketplace/channel yang ingin dipetakan (bisa satu channel saja).</p>
        </div>
        <div class="p-6">
            <form method="POST" action="{{ route('jubelio.sync.store') }}" class="space-y-6"
                  x-data="{
                    locations: @js($locations),
                    selectedLocationId: @js(old('location_id', $preselectedLocationId ?? '')),
                    preselectedStoreIds: @js($preselectedStoreIds ?? []),
                    stores: [],
                    selectedStoreIds: @js(array_map('intval', old('store_ids', $preselectedStoreIds ?? []))),
                    loadingStores: false,
                    storesError: '',
                    storesUrlTemplate: @js($storesUrlTemplate),
                    onLocationChange(id) {
                        this.selectedLocationId = id;
                        const loc = this.locations.find(l => String(l.location_id) === String(id));
                        document.getElementById('location_id').value = id;
                        document.getElementById('location_name').value = loc ? loc.location_name : '';
                        this.loadStores(id);
                    },
                    loadStores(id) {
                        if (!id) {
                            this.stores = [];
                            this.selectedStoreIds = [];
                            return;
                        }
                        this.loadingStores = true;
                        this.storesError = '';
                        const url = this.storesUrlTemplate.replace('__LOCATION__', encodeURIComponent(id));
                        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(r => r.ok ? r.json() : Promise.reject())
                            .then(data => {
                                this.stores = data.stores || [];
                                const available = this.stores.filter(s => !s.mapped).map(s => s.store_id);
                                if (this.selectedStoreIds.length === 0 && this.preselectedStoreIds.length) {
                                    this.selectedStoreIds = this.preselectedStoreIds.filter(id => available.includes(id));
                                } else {
                                    this.selectedStoreIds = this.selectedStoreIds.filter(id => available.includes(id));
                                }
                            })
                            .catch(() => { this.storesError = 'Gagal memuat daftar marketplace.'; this.stores = []; })
                            .finally(() => { this.loadingStores = false; });
                    },
                    toggleStore(id) {
                        if (this.selectedStoreIds.includes(id)) {
                            this.selectedStoreIds = this.selectedStoreIds.filter(x => x !== id);
                        } else {
                            this.selectedStoreIds.push(id);
                        }
                    },
                    canSubmit() {
                        return document.getElementById('warehouse_id').value && this.selectedStoreIds.length > 0 && this.selectedLocationId;
                    },
                    init() {
                        if (this.selectedLocationId) {
                            this.onLocationChange(this.selectedLocationId);
                        }
                    }
                  }"
                  x-init="init()">
                @csrf
                <input type="hidden" name="location_id" id="location_id" value="{{ old('location_id', $preselectedLocationId ?? '') }}">
                <input type="hidden" name="location_name" id="location_name" value="{{ old('location_name') }}">
                <input type="hidden" name="warehouse_id" id="warehouse_id" value="{{ old('warehouse_id') }}">
                <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id') }}">
                <template x-for="storeId in selectedStoreIds" :key="storeId">
                    <input type="hidden" name="store_ids[]" :value="storeId">
                </template>

                {{-- Jubelio Location --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Jubelio Location</label>
                    <select @change="onLocationChange($event.target.value)"
                            x-model="selectedLocationId"
                            class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option value="">Choose a Jubelio location</option>
                        @foreach($locations as $loc)
                        <option value="{{ $loc['location_id'] }}">{{ $loc['location_name'] }}</option>
                        @endforeach
                    </select>
                    @error('location_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                    @error('location_name')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Marketplace checkboxes --}}
                <div class="space-y-2" x-show="selectedLocationId" x-cloak>
                    <label class="block text-sm font-medium text-gray-700">Marketplace / channel Jubelio</label>
                    <p class="text-xs text-gray-500" x-show="loadingStores">Memuat dari Jubelio…</p>
                    <p class="text-sm text-red-500" x-show="storesError" x-text="storesError"></p>
                    <div class="max-h-56 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3" x-show="!loadingStores && stores.length">
                        <template x-for="store in stores" :key="store.store_id">
                            <label class="flex cursor-pointer items-start gap-3 rounded-md p-2 hover:bg-gray-50"
                                   :class="store.mapped ? 'opacity-50 cursor-not-allowed' : ''">
                                <input type="checkbox"
                                       class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                       :disabled="store.mapped"
                                       :checked="selectedStoreIds.includes(store.store_id)"
                                       @change="toggleStore(store.store_id)">
                                <span>
                                    <span class="block text-sm font-medium text-gray-900" x-text="store.store_name"></span>
                                    <span class="text-xs text-gray-400" x-show="store.mapped">Sudah dipetakan</span>
                                </span>
                            </label>
                        </template>
                    </div>
                    <p class="text-sm text-gray-400 italic" x-show="!loadingStores && selectedLocationId && stores.length === 0 && !storesError">Tidak ada marketplace di lokasi ini.</p>
                    @error('store_ids')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                    @error('store_ids.*')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Warehouse combobox --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Target Warehouse (Internal)</label>
                    @include('jubelio.partials.lookup-combobox', ['endpoint' => $warehouseRoute, 'placeholder' => 'Search warehouse...', 'hiddenField' => 'warehouse_id'])
                    @error('warehouse_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Customer combobox --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Target Customer (Channel)</label>
                    <p class="text-xs text-gray-500">Wajib untuk order Jubelio masuk (sell/return).</p>
                    @include('jubelio.partials.lookup-combobox', ['endpoint' => $customerRoute, 'placeholder' => 'Search customer...', 'hiddenField' => 'customer_id'])
                    @error('customer_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <a href="{{ route('jubelio.sync.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit"
                            :disabled="!canSubmit()"
                            class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                        Create Mapping
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
