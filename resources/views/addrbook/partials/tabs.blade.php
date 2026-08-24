@php
    // $addrbook and $active (one of: detail, transactions, items, stats, item-sales)
    $slug = $addrbook->type_slug;
    $id = $addrbook->id;
    $typePermissions = \App\Models\Addrbook::getPermissions($slug);
    $hasWarehouseStock = \App\Models\Addrbook::typeHasWarehouseStock((int) $addrbook->type);
    $supportsItemSales = \App\Models\Addrbook::typeSupportsItemSales((int) $addrbook->type);
    $warehouseItemsPermission = $typePermissions['warehouse-items'] ?? null;
    $itemSalesPermission = $typePermissions['item-sales'] ?? null;
    $canWarehouseItems = $hasWarehouseStock && $warehouseItemsPermission && (auth()->user()?->can($warehouseItemsPermission) ?? false);
    $canItemSales = $supportsItemSales && $itemSalesPermission && (auth()->user()?->can($itemSalesPermission) ?? false);
    $tabBase = 'border-b-2 px-6 py-4 text-sm font-medium whitespace-nowrap transition-all';
    $tabActive = 'border-blue-600 text-blue-600';
    $tabIdle = 'border-transparent text-gray-500 hover:border-gray-200 hover:text-gray-700';
@endphp
<div class="mb-6 flex overflow-x-auto border-b border-gray-200">
    <a href="/{{ $slug }}/{{ $id }}" class="{{ $tabBase }} {{ $active === 'detail' ? $tabActive : $tabIdle }}">Detail</a>
    <a href="/{{ $slug }}/{{ $id }}/transactions" class="{{ $tabBase }} {{ $active === 'transactions' ? $tabActive : $tabIdle }}">Transaction</a>
    @if($canWarehouseItems)
        <a href="/{{ $slug }}/{{ $id }}/items" class="{{ $tabBase }} {{ $active === 'items' ? $tabActive : $tabIdle }}">Items</a>
    @endif
    @if($hasWarehouseStock)
        <a href="/{{ $slug }}/{{ $id }}/stats" class="{{ $tabBase }} {{ $active === 'stats' ? $tabActive : $tabIdle }}">Stats</a>
    @endif
    @if($canItemSales)
        <a href="/{{ $slug }}/{{ $id }}/item-sales" class="{{ $tabBase }} {{ $active === 'item-sales' ? $tabActive : $tabIdle }}">Item Sale</a>
    @endif
</div>
