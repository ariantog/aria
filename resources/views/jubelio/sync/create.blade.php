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
        </div>
        <div class="p-6">
            <form method="POST" action="{{ route('jubelio.sync.store') }}" class="space-y-6"
                  x-data="{
                    locations: @js($locations),
                    onLocationChange(id) {
                        const loc = this.locations.find(l => String(l.location_id) === String(id));
                        document.getElementById('location_id').value = id;
                        document.getElementById('location_name').value = loc ? loc.location_name : '';
                    }
                  }">
                @csrf
                <input type="hidden" name="location_id" id="location_id" value="{{ old('location_id') }}">
                <input type="hidden" name="location_name" id="location_name" value="{{ old('location_name') }}">
                <input type="hidden" name="warehouse_id" id="warehouse_id" value="{{ old('warehouse_id') }}">
                <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id') }}">

                {{-- Jubelio Location --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Jubelio Location</label>
                    <select @change="onLocationChange($event.target.value)"
                            class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option value="">Choose a Jubelio location</option>
                        @foreach($locations as $loc)
                        <option value="{{ $loc['location_id'] }}" {{ old('location_id') == $loc['location_id'] ? 'selected' : '' }}>{{ $loc['location_name'] }}</option>
                        @endforeach
                    </select>
                    @error('location_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                    @error('location_name')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Warehouse combobox --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Target Warehouse (Internal)</label>
                    @include('jubelio.partials.lookup-combobox', ['endpoint' => $warehouseRoute, 'placeholder' => 'Search warehouse...', 'hiddenField' => 'warehouse_id'])
                    @error('warehouse_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Customer combobox --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Target Customer (Optional)</label>
                    @include('jubelio.partials.lookup-combobox', ['endpoint' => $customerRoute, 'placeholder' => 'Search customer...', 'hiddenField' => 'customer_id'])
                    @error('customer_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <a href="{{ route('jubelio.sync.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Create Mapping</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
