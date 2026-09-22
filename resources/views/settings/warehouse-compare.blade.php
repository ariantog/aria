@extends('layouts.app')
@section('title', 'Warehouse compare defaults')

@push('settings-content')
    <div class="space-y-6">
        <header>
            <h2 class="text-base font-medium text-gray-900">Warehouse compare</h2>
            <p class="text-sm text-gray-500">
                Default warehouses, item type, and sort for
                <a href="{{ route('reports.warehouse-compare') }}" class="text-blue-600 hover:underline">Warehouse stock compare</a>.
                The first warehouse is always the pivot (SKU list).
            </p>
        </header>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('warehouse-compare-settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-gray-900">Warehouses (up to {{ $maxWarehouses }})</p>
                @for($i = 0; $i < $maxWarehouses; $i++)
                    <div>
                        <label for="wh-slot-{{ $i }}" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">
                            @if($i === 0) Pivot @else Compare {{ $i }} @endif
                        </label>
                        <select id="wh-slot-{{ $i }}" name="warehouse_ids[]"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="">— None —</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_ids.'.$i, $slots[$i]) === (int) $warehouse->id)>
                                    {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endfor
            </section>

            <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div>
                    <label for="default-item-type" class="mb-1 block text-sm font-medium text-gray-700">Default item type</label>
                    <select id="default-item-type" name="item_type" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @foreach($itemTypeOptions as $value => $label)
                            <option value="{{ $value }}" @selected((string) old('item_type', $itemType) === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="default-sort" class="mb-1 block text-sm font-medium text-gray-700">Default sort</label>
                    <select id="default-sort" name="sort" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @foreach($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('sort', $sort) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </section>

            <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                Save defaults
            </button>
        </form>
    </div>
@endpush

@section('content')
    @php
        $breadcrumbs = [
            ['title' => 'Settings', 'href' => route('transaction-defaults.edit')],
            ['title' => 'Warehouse compare', 'href' => route('warehouse-compare-settings.edit')],
        ];
    @endphp
    @include('settings.partials.nav')
@endsection
