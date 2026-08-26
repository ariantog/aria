@props([
    'warehouseItems',
    'showZero' => 'showZero',
    'deleted' => false,
])

@if($warehouseItems->isEmpty())
    <div class="py-4 text-center text-sm italic text-gray-500">No stock in {{ $deleted ? 'deleted' : 'active' }} warehouses.</div>
@else
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach($warehouseItems as $wh)
        <div @class([
            'flex items-center justify-between rounded-xl border p-4',
            'border-gray-200 bg-gray-50' => ! $deleted,
            'border-rose-200 bg-rose-50/40' => $deleted,
            'opacity-60' => $wh->quantity < 1,
        ])
             @if($wh->quantity < 1) x-show="{{ $showZero }}" @endif>
            <div class="min-w-0">
                @if($wh->warehouse && ! $deleted)
                    <a href="{{ url('/'.$wh->warehouse->type_slug.'/'.$wh->warehouse->id) }}" class="block truncate font-medium text-blue-600 hover:underline">{{ $wh->warehouse->name }}</a>
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
                    'text-blue-600' => ! $deleted && $wh->quantity > 0,
                    'text-rose-700' => $deleted && $wh->quantity > 0,
                    'text-gray-400' => $wh->quantity < 1,
                ])>{{ number_format($wh->quantity, 0, ',', '.') }}</p>
                <p class="text-[10px] font-bold uppercase text-gray-400">{{ $wh->quantity > 0 ? 'Units' : 'Out of Stock' }}</p>
            </div>
        </div>
        @endforeach
    </div>
@endif
