@props([
    'warehouseItems',
    'showZero' => 'showZero',
    'deleted' => false,
    'variant' => null,
])
@php
    $variant = $variant ?? ($deleted ? 'deleted' : 'physical');
    $isDeleted = $variant === 'deleted';
    $isVirtual = $variant === 'virtual';
    $emptyLabel = match ($variant) {
        'deleted' => 'deleted',
        'virtual' => 'virtual',
        default => 'active',
    };
@endphp

@if($warehouseItems->isEmpty())
    <div class="py-4 text-center text-sm italic text-gray-500">No stock in {{ $emptyLabel }} warehouses.</div>
@else
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach($warehouseItems as $wh)
        @php $qty = (float) $wh->quantity; @endphp
        <div @class([
            'flex items-center justify-between rounded-xl border p-4',
            'border-gray-200 bg-gray-50' => $variant === 'physical',
            'border-violet-200 bg-violet-50/40' => $isVirtual,
            'border-rose-200 bg-rose-50/40' => $isDeleted,
            'opacity-60' => $qty == 0.0,
        ])
             @if($qty == 0.0) x-show="{{ $showZero }}" @endif>
            <div class="min-w-0">
                @if($wh->warehouse && $variant === 'physical')
                    <a href="{{ url('/'.$wh->warehouse->type_slug.'/'.$wh->warehouse->id) }}" class="block truncate font-medium text-blue-600 hover:underline">{{ $wh->warehouse->name }}</a>
                @elseif($wh->warehouse && $isVirtual)
                    <a href="{{ url('/'.$wh->warehouse->type_slug.'/'.$wh->warehouse->id) }}" class="block truncate font-medium text-violet-800 hover:underline">{{ $wh->warehouse->name }}</a>
                    <p class="text-[10px] font-bold uppercase tracking-wide text-violet-600">Virtual warehouse</p>
                @elseif($wh->warehouse)
                    <p class="truncate font-medium text-rose-800">{{ $wh->warehouse->name }}</p>
                    <p class="text-[10px] font-bold uppercase tracking-wide text-rose-600">Deleted warehouse</p>
                @else
                    <p class="font-medium text-gray-900">Warehouse #{{ $wh->warehouse_id }}</p>
                @endif
                <p class="text-[10px] uppercase text-gray-500">ID: {{ $wh->warehouse_id }}</p>
            </div>
            <div class="text-right">
                <p @class([
                    'text-lg font-bold',
                    'text-blue-600' => $variant === 'physical' && $qty > 0,
                    'text-violet-700' => $isVirtual && $qty > 0,
                    'text-rose-700' => ($isDeleted && $qty > 0) || $qty < 0,
                    'text-gray-400' => $qty == 0.0,
                ])>{{ format_amount($qty, 0) }}</p>
                <p class="text-[10px] font-bold uppercase text-gray-400">{{ $qty == 0.0 ? 'Out of Stock' : 'Units' }}</p>
            </div>
        </div>
        @endforeach
    </div>
@endif
