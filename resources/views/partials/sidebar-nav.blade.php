@php
    $perms = $_sidebar['permissions'] ?? [];
    $roles = $_sidebar['roles'] ?? [];
    $addrbookTypes = $_sidebar['addrbook_types'] ?? [];
    $isSuperAdmin = in_array('superadmin', $roles);
    $currentUrl = request()->path();

    // Closures, not named functions: this partial can be compiled/included more
    // than once in a single PHP process (e.g. across requests in tests), and
    // named functions would trigger "Cannot redeclare" fatals.
    $hasPerm = function (string $perm) use ($perms): bool {
        return in_array($perm, $perms, true) || in_array('*', $perms, true);
    };

    $isActive = function (string $prefix) use ($currentUrl): bool {
        return str_starts_with('/' . ltrim($currentUrl, '/'), $prefix);
    };
@endphp

{{-- ── Dashboard ─────────────────────────────────────────────────────── --}}
<div class="mb-1">
    <a href="{{ route('dashboard') }}"
       class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
              {{ $isActive('/dashboard') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span x-show="sidebarOpen" x-cloak>Dashboard</span>
    </a>
</div>

{{-- ── Transactions ──────────────────────────────────────────────────── --}}
@if($hasPerm('transactions-list') || $hasPerm('transactions-create') || $isSuperAdmin)
@php $txActive = $isActive('/transactions'); @endphp
<div x-data="{ open: {{ $txActive ? 'true' : 'false' }} }" class="mb-1">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $txActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">Transactions</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @if($hasPerm('transactions-list') || $isSuperAdmin)
        <a href="{{ route('transactions.index') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'transactions' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">List All</a>
        @endif
        @if($hasPerm('transactions-type-buy') || $isSuperAdmin)
        <a href="{{ route('transactions.create', 'buy') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ str_starts_with($currentUrl,'transactions/buy') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Buy</a>
        @endif
        @if($hasPerm('transactions-type-sell') || $isSuperAdmin)
        <a href="{{ route('transactions.create', 'sell') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ str_starts_with($currentUrl,'transactions/sell') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Sell</a>
        @endif
        @if($hasPerm('transactions-type-move') || $isSuperAdmin)
        <a href="{{ route('transactions.create', 'move') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ str_starts_with($currentUrl,'transactions/move') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Move</a>
        @endif
        @if($hasPerm('transactions-type-cash-in') || $isSuperAdmin)
        <a href="{{ route('transactions.cash-in') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'transactions/cash-in' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Cash In</a>
        @endif
        @if($hasPerm('transactions-type-cash-out') || $isSuperAdmin)
        <a href="{{ route('transactions.cash-out') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'transactions/cash-out' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Cash Out</a>
        @endif
        @if($hasPerm('transactions-type-transfer') || $isSuperAdmin)
        <a href="{{ route('transactions.transfer') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'transactions/transfer' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Transfer</a>
        @endif
        @if($hasPerm('transactions-type-adjust') || $isSuperAdmin)
        <a href="{{ route('transactions.adjust') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'transactions/adjust' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Adjust</a>
        @endif
        @if($hasPerm('transactions-type-return') || $isSuperAdmin)
        <a href="{{ route('transactions.create', 'return') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ str_starts_with($currentUrl,'transactions/return/') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Return</a>
        @endif
        @if($hasPerm('transactions-type-return-supplier') || $isSuperAdmin)
        <a href="{{ route('transactions.create', 'return-supplier') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ str_starts_with($currentUrl,'transactions/return-supplier/') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Return Supplier</a>
        @endif
    </div>
</div>
@endif

{{-- ── Address Book ──────────────────────────────────────────────────── --}}
@if($hasPerm('addrbook-list') || $isSuperAdmin || count($addrbookTypes))
@php $abActive = $isActive('/addrbook'); @endphp
<div x-data="{ open: {{ $abActive ? 'true' : 'false' }} }" class="mb-1">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $abActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">Address Book</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @if($hasPerm('addrbook-list') || $isSuperAdmin)
        <a href="{{ url('/addrbook') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'addrbook' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">All Contacts</a>
        @endif
        @foreach($addrbookTypes as $type)
            @if($hasPerm($type['permission']) || $isSuperAdmin)
            <a href="{{ url('/'.$type['slug']) }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/'.$type['slug']) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">{{ $type['name'] }}</a>
            @endif
        @endforeach
    </div>
</div>
@endif

{{-- ── Stuff (Items, etc.) ───────────────────────────────────────────── --}}
@if($hasPerm('items-list') || $hasPerm('restock-list') || $isSuperAdmin)
@php $stuffActive = $isActive('/items') || $isActive('/assetlancar') || $isActive('/tags') || $isActive('/restock'); @endphp
<div x-data="{ open: {{ $stuffActive ? 'true' : 'false' }} }" class="mb-1">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $stuffActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">Stuff</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @if($hasPerm('items-list') || $isSuperAdmin)
        <a href="{{ route('items.index') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/items') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Item</a>
        @endif
        @if($hasPerm('assetLancar-list') || $isSuperAdmin)
        <a href="{{ route('assetlancar.index') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/assetlancar') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Asset Lancar</a>
        @endif
        @if($hasPerm('stuff-group-list') || $isSuperAdmin)
        <a href="{{ route('items.group') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/items-group') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Group</a>
        @endif
        @if($hasPerm('stuff-tag-list') || $isSuperAdmin)
        <a href="{{ route('tags.index') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/tags') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Tags</a>
        @endif
        @if($hasPerm('items-contributor') || $isSuperAdmin)
        <a href="{{ route('contributors.index') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/contributors') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Contributors</a>
        @endif
        @if($hasPerm('restock-list') || $isSuperAdmin)
        <a href="{{ route('restock.index') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/restock') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Restock</a>
        @endif
    </div>
</div>
@endif

{{-- ── Reports ───────────────────────────────────────────────────────── --}}
@if($hasPerm('report-nett-cash') || $isSuperAdmin)
@php $repActive = $isActive('/reports'); @endphp
<div x-data="{ open: {{ $repActive ? 'true' : 'false' }} }" class="mb-1">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $repActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">Reports</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        <a href="{{ route('reports.nett-cash-sby') }}" class="block rounded-md px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Nett Cash</a>
        @if($hasPerm('report-cash-flow') || $isSuperAdmin)
        <a href="{{ route('reports.cash-flow') }}" class="block rounded-md px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Cash Flow</a>
        @endif
        @if($hasPerm('report-compare') || $isSuperAdmin)
        <a href="{{ route('reports.compare') }}" class="block rounded-md px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Compare</a>
        @endif
        @if($hasPerm('report-item-sales') || $isSuperAdmin)
        <a href="{{ route('reports.item-sales') }}" class="block rounded-md px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Item Sale</a>
        @endif
        @if($hasPerm('report-purchase') || $isSuperAdmin)
        <a href="{{ route('reports.purchase') }}" class="block rounded-md px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Pembelian</a>
        @endif
        @if($hasPerm('report-warehouse-item') || $isSuperAdmin)
        <a href="{{ route('reports.warehouse-item') }}" class="block rounded-md px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Item Gudang</a>
        @endif
        @if($hasPerm('report-expense') || $isSuperAdmin)
        <a href="{{ route('reports.expense') }}" class="block rounded-md px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Laporan Biaya</a>
        @endif
        @if($hasPerm('report-stock-intelligence') || $isSuperAdmin)
        <a href="{{ route('reports.stock-intelligence') }}" class="block rounded-md px-2.5 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Stock Intelegen</a>
        @endif
    </div>
</div>
@endif

{{-- ── System Settings ───────────────────────────────────────────────── --}}
@if($hasPerm('setting-general-view') || $hasPerm('setting-cron-manager-view') || $isSuperAdmin)
<div class="mb-1">
    <a href="{{ route('system-settings.index') }}"
       class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
              {{ $isActive('/system-settings') || $isActive('/cron-manager') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <span x-show="sidebarOpen" x-cloak>System Settings</span>
    </a>
</div>
@endif

{{-- ── User Management ───────────────────────────────────────────────── --}}
@if($hasPerm('users-list') || $hasPerm('users-roles-list') || $isSuperAdmin)
@php $umActive = $isActive('/users') || $isActive('/roles') || $isActive('/permissions'); @endphp
<div x-data="{ open: {{ $umActive ? 'true' : 'false' }} }" class="mb-1">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $umActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">User Management</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @if($hasPerm('users-list') || $isSuperAdmin)
        <a href="{{ route('users.index') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/users') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Users</a>
        @endif
        @if($hasPerm('users-roles-list') || $isSuperAdmin)
        <a href="{{ route('roles.index') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/roles') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Roles</a>
        @endif
        @if($hasPerm('users-permissions-list') || $isSuperAdmin)
        <a href="{{ route('permissions.index') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/permissions') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Permissions</a>
        @endif
        @if($hasPerm('users-locations-list') || $isSuperAdmin)
        <a href="{{ route('locations.index') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/locations') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Locations</a>
        @endif
    </div>
</div>
@endif
