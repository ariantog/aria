@php
    // $addrbook and $active (one of: detail, transactions, items, stats, item-sales)
    $slug = $addrbook->type_slug;
    $id = $addrbook->id;
    $isWarehouse = $addrbook->type instanceof \App\Enums\AddrbookType && $addrbook->type->isWarehouse();
    $tabBase = 'border-b-2 px-6 py-4 text-sm font-medium whitespace-nowrap transition-all';
    $tabActive = 'border-blue-600 text-blue-600';
    $tabIdle = 'border-transparent text-gray-500 hover:border-gray-200 hover:text-gray-700';
@endphp
<div class="mb-6 flex overflow-x-auto border-b border-gray-200">
    <a href="/{{ $slug }}/{{ $id }}" class="{{ $tabBase }} {{ $active === 'detail' ? $tabActive : $tabIdle }}">Detail</a>
    <a href="/{{ $slug }}/{{ $id }}/transactions" class="{{ $tabBase }} {{ $active === 'transactions' ? $tabActive : $tabIdle }}">Transaction</a>
    @if($isWarehouse)
        <a href="/{{ $slug }}/{{ $id }}/items" class="{{ $tabBase }} {{ $active === 'items' ? $tabActive : $tabIdle }}">Items</a>
        <a href="/{{ $slug }}/{{ $id }}/stats" class="{{ $tabBase }} {{ $active === 'stats' ? $tabActive : $tabIdle }}">Stats</a>
    @endif
    <a href="/{{ $slug }}/{{ $id }}/item-sales" class="{{ $tabBase }} {{ $active === 'item-sales' ? $tabActive : $tabIdle }}">Item Sale</a>
</div>
