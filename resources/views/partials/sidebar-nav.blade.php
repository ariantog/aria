@php
    $perms = $_sidebar['permissions'] ?? [];
    $roles = $_sidebar['roles'] ?? [];
    $addrbookTypes = $_sidebar['addrbook_types'] ?? [];
    // User ID 1 is the one and only superadmin.
    $isSuperAdmin = (bool) (auth()->user()?->is_superadmin);
    $currentUrl = request()->path();

    // Closures, not named functions: this partial can be compiled/included more
    // than once in a single PHP process (e.g. across requests in tests), and
    // named functions would trigger "Cannot redeclare" fatals.
    $hasPerm = function (string $perm) use ($perms, $isSuperAdmin): bool {
        if ($isSuperAdmin || in_array('*', $perms, true)) {
            return true;
        }

        if (in_array($perm, $perms, true)) {
            return true;
        }

        // Fall back to Spatie's resolver so role grants are always honored.
        return auth()->user()?->can($perm) ?? false;
    };

    $isActive = function (string $prefix) use ($currentUrl): bool {
        return str_starts_with('/' . ltrim($currentUrl, '/'), $prefix);
    };

    $visibleAddrbookTypes = collect($addrbookTypes)->filter(
        fn ($type) => $hasPerm($type['permission']) || $isSuperAdmin
    );

    $txNavLabels = ['Transactions'];
    if ($hasPerm('transactions-list') || $isSuperAdmin) {
        $txNavLabels[] = 'List All';
    }
    if ($hasPerm('transactions-type-buy') || $isSuperAdmin) {
        $txNavLabels[] = 'Buy';
    }
    if ($hasPerm('transactions-type-sell') || $isSuperAdmin) {
        $txNavLabels[] = 'Sell';
    }
    if ($hasPerm('transactions-type-move') || $isSuperAdmin) {
        $txNavLabels[] = 'Move';
    }
    if ($hasPerm('transactions-type-cash-in') || $isSuperAdmin) {
        $txNavLabels[] = 'Cash In';
    }
    if ($hasPerm('transactions-type-cash-out') || $isSuperAdmin) {
        $txNavLabels[] = 'Cash Out';
    }
    if ($hasPerm('transactions-type-transfer') || $isSuperAdmin) {
        $txNavLabels[] = 'Transfer';
    }
    if ($hasPerm('transactions-type-adjust') || $isSuperAdmin) {
        $txNavLabels[] = 'Adjust';
    }
    if ($hasPerm('transactions-type-return') || $isSuperAdmin) {
        $txNavLabels[] = 'Return';
    }
    if ($hasPerm('transactions-type-return-supplier') || $isSuperAdmin) {
        $txNavLabels[] = 'Return Supplier';
    }
    if ($hasPerm('report-purchase') || $isSuperAdmin) {
        $txNavLabels[] = 'Pembelian';
    }
    if ($hasPerm('report-export-sell') || $isSuperAdmin) {
        $txNavLabels[] = 'Export Sell';
    }

    $abNavLabels = ['Address Book', ...$visibleAddrbookTypes->pluck('name')->all()];

    $stuffNavLabels = ['Stuff'];
    if ($hasPerm('items-list') || $isSuperAdmin) {
        $stuffNavLabels[] = 'Item';
    }
    if ($hasPerm('assetLancar-list') || $isSuperAdmin) {
        $stuffNavLabels[] = 'Asset Lancar';
    }
    if ($hasPerm('stuff-group-list') || $isSuperAdmin) {
        $stuffNavLabels[] = 'Group';
    }
    if ($hasPerm('stuff-tag-list') || $isSuperAdmin) {
        $stuffNavLabels[] = 'Tags';
    }
    if ($hasPerm('restock-list') || $isSuperAdmin) {
        $stuffNavLabels[] = 'Restock';
    }
    if ($hasPerm('items-convert-legacy') || $isSuperAdmin) {
        $stuffNavLabels[] = 'Legacy Converter';
        $stuffNavLabels[] = 'Special Converter';
    }
    if ($hasPerm('stock-notification-list') || $isSuperAdmin) {
        $stuffNavLabels[] = 'Stock Alerts';
    }

    $reportNavLabels = ['Reports'];
    if ($hasPerm('report-item-sales') || $isSuperAdmin) {
        $reportNavLabels[] = 'Item Sale';
    }
    if ($hasPerm('report-warehouse-item') || $isSuperAdmin) {
        $reportNavLabels[] = 'Item Gudang';
    }
    if ($hasPerm('report-compare') || $isSuperAdmin) {
        $reportNavLabels[] = 'Compare';
    }
    if ($hasPerm('report-warehouse-arrangement') || $isSuperAdmin) {
        $reportNavLabels[] = 'Warehouse Arrangement';
    }
    if ($hasPerm('report-product-performance') || $isSuperAdmin) {
        $reportNavLabels[] = 'Product Performance';
    }
    if ($hasPerm('report-inventory-health') || $isSuperAdmin) {
        $reportNavLabels[] = 'Inventory Health';
    }
    if ($hasPerm('report-nett-cash') || $isSuperAdmin) {
        $reportNavLabels[] = 'Nett Cash';
    }
    if ($hasPerm('report-cash-flow') || $isSuperAdmin) {
        $reportNavLabels[] = 'Cash Flow';
    }
    if ($hasPerm('report-expense') || $isSuperAdmin) {
        $reportNavLabels[] = 'Laporan Biaya';
    }
    if ($hasPerm('report-tax-ppn') || $isSuperAdmin) {
        $reportNavLabels[] = 'Laporan PPN';
    }
    if ($hasPerm('report-tax-faktur') || $hasPerm('report-tax-faktur-import') || $isSuperAdmin) {
        $reportNavLabels[] = 'Faktur Pajak';
    }
    if ($hasPerm('report-produksi-potong') || $isSuperAdmin) {
        $reportNavLabels[] = 'Statistik Potong';
    }
    if ($hasPerm('report-produksi-qc') || $isSuperAdmin) {
        $reportNavLabels[] = 'Statistik QC';
    }
    if ($isSuperAdmin) {
        $reportNavLabels[] = 'Reporting Entities';
    }

    $jubelioNavLabels = ['Jubelio'];
    if ($hasPerm('jubelio-view') || $isSuperAdmin) {
        $jubelioNavLabels = array_merge($jubelioNavLabels, ['Orders', 'Cancellations', 'Get Orders', 'Cek Order', 'Koneksi']);
    }
    if ($hasPerm('jubelio-sync') || $isSuperAdmin) {
        $jubelioNavLabels[] = 'Stock Sync';
        $jubelioNavLabels[] = 'Warehouse Map';
    }
    if ($hasPerm('jubelio-stock-check') || $isSuperAdmin) {
        $jubelioNavLabels[] = 'Stock Check';
    }

    $journalNavLabels = ['Journals'];
    if ($hasPerm('journal-account-list') || $isSuperAdmin) {
        $journalNavLabels[] = 'Accounts';
    }
    if ($hasPerm('journal-operation-list') || $isSuperAdmin) {
        $journalNavLabels[] = 'Operations';
    }

    $produksiNavLabels = ['Produksi'];
    if ($hasPerm('production-list') || $isSuperAdmin) {
        $produksiNavLabels[] = 'Production';
    }
    if ($hasPerm('production-setoran-list') || $isSuperAdmin) {
        $produksiNavLabels[] = 'Setoran';
    }
    if ($hasPerm('production-worker-list') || $isSuperAdmin) {
        $produksiNavLabels = array_merge($produksiNavLabels, ['Potong Workers', 'Jahit Workers', 'QC Workers', 'Pritil Workers']);
    }

    $systemNavLabels = ['System Settings'];
    if ($hasPerm('setting-general-view') || $isSuperAdmin) {
        $systemNavLabels[] = 'General';
    }
    if ($hasPerm('setting-cron-manager-view') || $isSuperAdmin) {
        $systemNavLabels[] = 'Cron Manager';
    }
    if ($isSuperAdmin) {
        $systemNavLabels[] = 'Data Retention';
        $systemNavLabels[] = 'Selective Item Purge';
    }

    $archiveNavLabels = ['Archive'];
    if ($hasPerm('archive-view') || $isSuperAdmin) {
        $archiveNavLabels[] = 'Overview';
        $archiveNavLabels[] = 'Transactions';
        $archiveNavLabels[] = 'Items';
    }

    $userNavLabels = ['User Management'];
    if ($hasPerm('users-list') || $isSuperAdmin) {
        $userNavLabels[] = 'Users';
    }
    if ($hasPerm('users-roles-list') || $isSuperAdmin) {
        $userNavLabels[] = 'Roles';
    }
    if ($hasPerm('users-permissions-list') || $isSuperAdmin) {
        $userNavLabels[] = 'Permissions';
    }
    if ($hasPerm('users-locations-list') || $isSuperAdmin) {
        $userNavLabels[] = 'Locations';
    }

    $hrNavLabels = ['HR / Payroll'];
    if ($hasPerm('karyawan-list') || $isSuperAdmin) {
        $hrNavLabels[] = 'Karyawan';
    }
    if ($hasPerm('karyawan-gaji-list') || $isSuperAdmin) {
        $hrNavLabels[] = 'Gaji';
    }

    $sidebarFavorites = $_sidebar['favorites'] ?? [];
    $favoriteNavLabels = ['Favorites', ...collect($sidebarFavorites)->pluck('label')->all()];
    $favoritesActive = collect($sidebarFavorites)->contains(
        fn ($favorite) => $isActive($favorite['active_prefix'])
    );
@endphp

{{-- ── Dashboard ─────────────────────────────────────────────────────── --}}
<div class="mb-1" x-show="navLinkVisible('Dashboard')">
    <a href="{{ route('dashboard') }}"
       class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
              {{ $isActive('/dashboard') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span x-show="sidebarOpen" x-cloak>Dashboard</span>
    </a>
</div>

{{-- ── Favorites ─────────────────────────────────────────────────────── --}}
@if(count($sidebarFavorites) > 0)
<div x-data="{ open: {{ $favoritesActive ? 'true' : 'false' }} }"
     class="mb-1"
     x-show="navGroupVisible(@js($favoriteNavLabels))"
     x-effect="syncNavGroupOpen($data, {{ $favoritesActive ? 'true' : 'false' }}, @js($favoriteNavLabels))">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $favoritesActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">Favorites</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @foreach($sidebarFavorites as $favorite)
            <a href="{{ $favorite['url'] }}"
               x-show="navLinkVisible(@js($favorite['label']), 'Favorites')"
               class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive($favorite['active_prefix']) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                {{ $favorite['label'] }}
            </a>
        @endforeach
    </div>
</div>
@endif

{{-- ── Transactions ──────────────────────────────────────────────────── --}}
@if(
    $hasPerm('transactions-list')
    || $hasPerm('transactions-create')
    || $hasPerm('transactions-type-buy')
    || $hasPerm('transactions-type-sell')
    || $hasPerm('transactions-type-move')
    || $hasPerm('transactions-type-cash-in')
    || $hasPerm('transactions-type-cash-out')
    || $hasPerm('transactions-type-transfer')
    || $hasPerm('transactions-type-adjust')
    || $hasPerm('transactions-type-return')
    || $hasPerm('transactions-type-return-supplier')
    || $hasPerm('report-purchase')
    || $hasPerm('report-export-sell')
    || $isSuperAdmin
)
@php $txActive = $isActive('/transactions') || $isActive('/reports/purchase'); @endphp
<div x-data="{ open: {{ $txActive ? 'true' : 'false' }} }"
     class="mb-1"
     x-show="navGroupVisible(@js($txNavLabels))"
     x-effect="syncNavGroupOpen($data, {{ $txActive ? 'true' : 'false' }}, @js($txNavLabels))">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $txActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">Transactions</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @if($hasPerm('transactions-list') || $isSuperAdmin)
        <a href="{{ route('transactions.index') }}" x-show="navLinkVisible('List All', 'Transactions')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'transactions' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">List All</a>
        @endif
        @if($hasPerm('transactions-type-buy') || $isSuperAdmin)
        <a href="{{ route('transactions.create', 'buy') }}" x-show="navLinkVisible('Buy', 'Transactions')" class="block rounded-md px-2.5 py-1.5 text-sm {{ str_starts_with($currentUrl,'transactions/buy') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Buy</a>
        @endif
        @if($hasPerm('transactions-type-sell') || $isSuperAdmin)
        <a href="{{ route('transactions.create', 'sell') }}" x-show="navLinkVisible('Sell', 'Transactions')" class="block rounded-md px-2.5 py-1.5 text-sm {{ str_starts_with($currentUrl,'transactions/sell') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Sell</a>
        @endif
        @if($hasPerm('transactions-type-move') || $isSuperAdmin)
        <a href="{{ route('transactions.create', 'move') }}" x-show="navLinkVisible('Move', 'Transactions')" class="block rounded-md px-2.5 py-1.5 text-sm {{ str_starts_with($currentUrl,'transactions/move') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Move</a>
        @endif
        @if($hasPerm('transactions-type-cash-in') || $isSuperAdmin)
        <a href="{{ route('transactions.cash-in') }}" x-show="navLinkVisible('Cash In', 'Transactions')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'transactions/cash-in' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Cash In</a>
        @endif
        @if($hasPerm('transactions-type-cash-out') || $isSuperAdmin)
        <a href="{{ route('transactions.cash-out') }}" x-show="navLinkVisible('Cash Out', 'Transactions')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'transactions/cash-out' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Cash Out</a>
        @endif
        @if($hasPerm('transactions-type-transfer') || $isSuperAdmin)
        <a href="{{ route('transactions.transfer') }}" x-show="navLinkVisible('Transfer', 'Transactions')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'transactions/transfer' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Transfer</a>
        @endif
        @if($hasPerm('transactions-type-adjust') || $isSuperAdmin)
        <a href="{{ route('transactions.adjust') }}" x-show="navLinkVisible('Adjust', 'Transactions')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'transactions/adjust' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Adjust</a>
        @endif
        @if($hasPerm('transactions-type-return') || $isSuperAdmin)
        <a href="{{ route('transactions.create', 'return') }}" x-show="navLinkVisible('Return', 'Transactions')" class="block rounded-md px-2.5 py-1.5 text-sm {{ str_starts_with($currentUrl,'transactions/return/') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Return</a>
        @endif
        @if($hasPerm('transactions-type-return-supplier') || $isSuperAdmin)
        <a href="{{ route('transactions.create', 'return-supplier') }}" x-show="navLinkVisible('Return Supplier', 'Transactions')" class="block rounded-md px-2.5 py-1.5 text-sm {{ str_starts_with($currentUrl,'transactions/return-supplier/') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Return Supplier</a>
        @endif
        @if($hasPerm('report-purchase') || $isSuperAdmin)
        <a href="{{ route('reports.purchase') }}" x-show="navLinkVisible('Pembelian', 'Transactions')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/purchase') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Pembelian</a>
        @endif
        @if($hasPerm('report-export-sell') || $isSuperAdmin)
        <a href="{{ route('transactions.export-sell') }}" x-show="navLinkVisible('Export Sell', 'Transactions')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/transactions/export-sell') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Export Sell</a>
        @endif
    </div>
</div>
@endif

{{-- ── Invoice Maker ─────────────────────────────────────────────────── --}}
@if($hasPerm('invoice-maker-list') || $isSuperAdmin)
<div class="mb-1" x-show="navLinkVisible('Invoice Maker')">
    <a href="{{ route('invoice-maker.index') }}"
       class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
              {{ $isActive('/invoice-maker') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <span x-show="sidebarOpen" x-cloak>Invoice Maker</span>
    </a>
</div>
@endif

{{-- ── Address Book ──────────────────────────────────────────────────── --}}
@if($isSuperAdmin || $visibleAddrbookTypes->isNotEmpty())
@php $abActive = $isActive('/addrbook') || $visibleAddrbookTypes->contains(fn ($type) => $isActive('/'.$type['slug'])); @endphp
<div x-data="{ open: {{ $abActive ? 'true' : 'false' }} }"
     class="mb-1"
     x-show="navGroupVisible(@js($abNavLabels))"
     x-effect="syncNavGroupOpen($data, {{ $abActive ? 'true' : 'false' }}, @js($abNavLabels))">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $abActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">Address Book</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @foreach($visibleAddrbookTypes as $type)
            <a href="{{ url('/'.$type['slug']) }}" x-show="navLinkVisible(@js($type['name']), 'Address Book')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/'.$type['slug']) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">{{ $type['name'] }}</a>
        @endforeach
    </div>
</div>
@endif

{{-- ── Stuff (Items, etc.) ───────────────────────────────────────────── --}}
@if($hasPerm('items-list') || $hasPerm('assetLancar-list') || $hasPerm('stuff-group-list') || $hasPerm('stuff-tag-list') || $hasPerm('restock-list') || $hasPerm('items-convert-legacy') || $hasPerm('stock-notification-list') || $isSuperAdmin)
@php
    $stuffActive = $isActive('/items') || $isActive('/assetlancar') || $isActive('/tags') || $isActive('/restock')
        || $isActive('/stock-notifications');
@endphp
<div x-data="{ open: {{ $stuffActive ? 'true' : 'false' }} }"
     class="mb-1"
     x-show="navGroupVisible(@js($stuffNavLabels))"
     x-effect="syncNavGroupOpen($data, {{ $stuffActive ? 'true' : 'false' }}, @js($stuffNavLabels))">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $stuffActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">Stuff</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @if($hasPerm('items-list') || $isSuperAdmin)
        <a href="{{ route('items.index') }}" x-show="navLinkVisible('Item', 'Stuff')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/items') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Item</a>
        @endif
        @if($hasPerm('assetLancar-list') || $isSuperAdmin)
        <a href="{{ route('assetlancar.index') }}" x-show="navLinkVisible('Asset Lancar', 'Stuff')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/assetlancar') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Asset Lancar</a>
        @endif
        @if($hasPerm('stuff-group-list') || $isSuperAdmin)
        <a href="{{ route('items.group') }}" x-show="navLinkVisible('Group', 'Stuff')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/items-group') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Group</a>
        @endif
        @if($hasPerm('stuff-tag-list') || $isSuperAdmin)
        <a href="{{ route('tags.index') }}" x-show="navLinkVisible('Tags', 'Stuff')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/tags') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Tags</a>
        @endif
        @if($hasPerm('restock-list') || $isSuperAdmin)
        <a href="{{ route('restock.index') }}" x-show="navLinkVisible('Restock', 'Stuff')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/restock') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Restock</a>
        @endif
        @if($hasPerm('items-convert-legacy') || $isSuperAdmin)
        <a href="{{ route('items.legacy-converter') }}" x-show="navLinkVisible('Legacy Converter', 'Stuff')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/items/legacy-converter') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Legacy Converter</a>
        <a href="{{ route('items.special-converter') }}" x-show="navLinkVisible('Special Converter', 'Stuff')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/items/special-converter') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Special Converter</a>
        @endif
        @if($hasPerm('stock-notification-list') || $isSuperAdmin)
        <a href="{{ route('stock-notifications.index') }}" x-show="navLinkVisible('Stock Alerts', 'Stuff')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/stock-notifications') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Stock Alerts</a>
        @endif
    </div>
</div>
@endif

{{-- ── Reports ───────────────────────────────────────────────────────── --}}
@if(
    $hasPerm('report-purchase')
    || $hasPerm('report-export-sell')
    || $hasPerm('report-item-sales')
    || $hasPerm('report-warehouse-item')
    || $hasPerm('report-warehouse-arrangement')
    || $hasPerm('report-compare')
    || $hasPerm('report-product-performance')
    || $hasPerm('report-inventory-health')
    || $hasPerm('report-nett-cash')
    || $hasPerm('report-cash-flow')
    || $hasPerm('report-expense')
    || $hasPerm('report-tax-ppn')
    || $hasPerm('report-tax-faktur')
    || $hasPerm('report-tax-faktur-import')
    || $hasPerm('report-produksi-potong')
    || $hasPerm('report-produksi-qc')
    || $isSuperAdmin
)
@php
    $repActive = $isActive('/reports/purchase')
        || $isActive('/reports/item-sales')
        || $isActive('/reports/warehouse-item')
        || $isActive('/reports/warehouse-arrangement')
        || $isActive('/reports/compare')
        || $isActive('/reports/product-performance')
        || $isActive('/reports/inventory-health')
        || $isActive('/reports/nett-cash-sby')
        || $isActive('/reports/cash-flow')
        || $isActive('/reports/expense')
        || $isActive('/reports/tax/ppn')
        || $isActive('/reports/tax/faktur')
        || $isActive('/reports/produksi-potong')
        || $isActive('/reports/produksi-qc')
        || $isActive('/reports/entities');
@endphp
<div x-data="{ open: {{ $repActive ? 'true' : 'false' }} }"
     class="mb-1"
     x-show="navGroupVisible(@js($reportNavLabels))"
     x-effect="syncNavGroupOpen($data, {{ $repActive ? 'true' : 'false' }}, @js($reportNavLabels))">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $repActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">Reports</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @if($hasPerm('report-item-sales') || $hasPerm('report-warehouse-item') || $hasPerm('report-compare') || $hasPerm('report-warehouse-arrangement') || $hasPerm('report-product-performance') || $hasPerm('report-inventory-health') || $isSuperAdmin)
        <p class="px-2.5 pt-2 pb-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Inventory</p>
        @endif
        @if($hasPerm('report-item-sales') || $isSuperAdmin)
        <a href="{{ route('reports.item-sales') }}" x-show="navLinkVisible('Item Sale', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/item-sales') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Item Sale</a>
        @endif
        @if($hasPerm('report-warehouse-item') || $isSuperAdmin)
        <a href="{{ route('reports.warehouse-item') }}" x-show="navLinkVisible('Item Gudang', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/warehouse-item') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Item Gudang</a>
        @endif
        @if($hasPerm('report-compare') || $isSuperAdmin)
        <a href="{{ route('reports.compare') }}" x-show="navLinkVisible('Compare', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/compare') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Compare</a>
        @endif
        @if($hasPerm('report-warehouse-arrangement') || $isSuperAdmin)
        <a href="{{ route('reports.warehouse-arrangement') }}" x-show="navLinkVisible('Warehouse Arrangement', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/warehouse-arrangement') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Warehouse Arrangement</a>
        @endif
        @if($hasPerm('report-product-performance') || $isSuperAdmin)
        <a href="{{ route('reports.product-performance') }}" x-show="navLinkVisible('Product Performance', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/product-performance') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Product Performance</a>
        @endif
        @if($hasPerm('report-inventory-health') || $isSuperAdmin)
        <a href="{{ route('reports.inventory-health') }}" x-show="navLinkVisible('Inventory Health', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/inventory-health') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Inventory Health</a>
        @endif

        @if($hasPerm('report-nett-cash') || $hasPerm('report-cash-flow') || $hasPerm('report-expense') || $isSuperAdmin)
        <p class="px-2.5 pt-2 pb-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Finance</p>
        @endif
        @if($hasPerm('report-nett-cash') || $isSuperAdmin)
        <a href="{{ route('reports.nett-cash-sby') }}" x-show="navLinkVisible('Nett Cash', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/nett-cash-sby') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Nett Cash</a>
        @endif
        @if($hasPerm('report-cash-flow') || $isSuperAdmin)
        <a href="{{ route('reports.cash-flow') }}" x-show="navLinkVisible('Cash Flow', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/cash-flow') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Cash Flow</a>
        @endif
        @if($hasPerm('report-expense') || $isSuperAdmin)
        <a href="{{ route('reports.expense') }}" x-show="navLinkVisible('Laporan Biaya', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/expense') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Laporan Biaya</a>
        @endif

        @if($hasPerm('report-tax-ppn') || $hasPerm('report-tax-faktur') || $hasPerm('report-tax-faktur-import') || $isSuperAdmin)
        <p class="px-2.5 pt-2 pb-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Tax</p>
        @endif
        @if($hasPerm('report-tax-ppn') || $isSuperAdmin)
        <a href="{{ route('reports.tax.ppn') }}" x-show="navLinkVisible('Laporan PPN', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/tax/ppn') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Laporan PPN</a>
        @endif
        @if($hasPerm('report-tax-faktur') || $hasPerm('report-tax-faktur-import') || $isSuperAdmin)
        <a href="{{ route('reports.tax.faktur.index') }}" x-show="navLinkVisible('Faktur Pajak', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/tax/faktur') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Faktur Pajak</a>
        @endif

        @if($hasPerm('report-produksi-potong') || $hasPerm('report-produksi-qc') || $isSuperAdmin)
        <p class="px-2.5 pt-2 pb-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Production</p>
        @endif
        @if($hasPerm('report-produksi-potong') || $isSuperAdmin)
        <a href="{{ route('reports.produksi-potong') }}" x-show="navLinkVisible('Statistik Potong', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/produksi-potong') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Statistik Potong</a>
        @endif
        @if($hasPerm('report-produksi-qc') || $isSuperAdmin)
        <a href="{{ route('reports.produksi-qc') }}" x-show="navLinkVisible('Statistik QC', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/produksi-qc') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Statistik QC</a>
        @endif

        @if($isSuperAdmin)
        <p class="px-2.5 pt-2 pb-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Admin</p>
        <a href="{{ route('reports.entities.index') }}" x-show="navLinkVisible('Reporting Entities', 'Reports')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/reports/entities') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Reporting Entities</a>
        @endif
    </div>
</div>
@endif

{{-- ── Jubelio ───────────────────────────────────────────────────────── --}}
@if($hasPerm('jubelio-view') || $hasPerm('jubelio-sync') || $hasPerm('jubelio-stock-check') || $isSuperAdmin)
@php $jubActive = $isActive('/jubelio'); @endphp
<div x-data="{ open: {{ $jubActive ? 'true' : 'false' }} }"
     class="mb-1"
     x-show="navGroupVisible(@js($jubelioNavLabels))"
     x-effect="syncNavGroupOpen($data, {{ $jubActive ? 'true' : 'false' }}, @js($jubelioNavLabels))">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $jubActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">Jubelio</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @if($hasPerm('jubelio-view') || $isSuperAdmin)
        <a href="{{ route('jubelio.index') }}" x-show="navLinkVisible('Orders', 'Jubelio')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'jubelio' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Orders</a>
        <a href="{{ route('jubelio.returns.index') }}" x-show="navLinkVisible('Cancellations', 'Jubelio')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/jubelio-returns') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Cancellations</a>
        <a href="{{ route('jubelio.get-orders.index') }}" x-show="navLinkVisible('Get Orders', 'Jubelio')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/jubelio-get-orders') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Get Orders</a>
        <a href="{{ route('jubelio.order.cek') }}" x-show="navLinkVisible('Cek Order', 'Jubelio')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/jubelio/order/cek') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Cek Order</a>
        <a href="{{ route('jubelio.token.index') }}" x-show="navLinkVisible('Koneksi', 'Jubelio')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/jubelio/token') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Koneksi</a>
        @endif
        @if($hasPerm('jubelio-sync') || $isSuperAdmin)
        <a href="{{ route('jubelio.transaction.sync') }}" x-show="navLinkVisible('Stock Sync', 'Jubelio')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/jubelio-transaction') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Stock Sync</a>
        <a href="{{ route('jubelio.sync.index') }}" x-show="navLinkVisible('Warehouse Map', 'Jubelio')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/jubelio-sync') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Warehouse Map</a>
        @endif
        @if($hasPerm('jubelio-stock-check') || $isSuperAdmin)
        <a href="{{ route('jubelio-stock-checks.index') }}" x-show="navLinkVisible('Stock Check', 'Jubelio')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/jubelio-stock-checks') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Stock Check</a>
        @endif
    </div>
</div>
@endif

{{-- ── Shopee Ads ────────────────────────────────────────────────────── --}}
@if($hasPerm('shopee-ads-view') || $isSuperAdmin)
<div class="mb-1" x-show="navLinkVisible('Shopee Ads')">
    <a href="{{ route('shopee-ads.index') }}"
       class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
              {{ $isActive('/shopee-ads') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        <span x-show="sidebarOpen" x-cloak>Shopee Ads</span>
    </a>
</div>
@endif

{{-- ── Journals ──────────────────────────────────────────────────────── --}}
@if($hasPerm('journal-account-list') || $hasPerm('journal-operation-list') || $isSuperAdmin)
@php $jrnActive = $isActive('/journals'); @endphp
<div x-data="{ open: {{ $jrnActive ? 'true' : 'false' }} }"
     class="mb-1"
     x-show="navGroupVisible(@js($journalNavLabels))"
     x-effect="syncNavGroupOpen($data, {{ $jrnActive ? 'true' : 'false' }}, @js($journalNavLabels))">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $jrnActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">Journals</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @if($hasPerm('journal-account-list') || $isSuperAdmin)
        <a href="{{ route('account-list.index') }}" x-show="navLinkVisible('Accounts', 'Journals')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/journals/account-list') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Accounts</a>
        @endif
        @if($hasPerm('journal-operation-list') || $isSuperAdmin)
        <a href="{{ route('operations.index') }}" x-show="navLinkVisible('Operations', 'Journals')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/journals/operations') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Operations</a>
        @endif
    </div>
</div>
@endif

{{-- ── Produksi ──────────────────────────────────────────────────────── --}}
@if($hasPerm('production-list') || $hasPerm('production-setoran-list') || $hasPerm('production-worker-list') || $isSuperAdmin)
@php
    $prdActive = $isActive('/produksi');
@endphp
<div x-data="{ open: {{ $prdActive ? 'true' : 'false' }} }"
     class="mb-1"
     x-show="navGroupVisible(@js($produksiNavLabels))"
     x-effect="syncNavGroupOpen($data, {{ $prdActive ? 'true' : 'false' }}, @js($produksiNavLabels))">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $prdActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 15.536c-1.171 1.952-3.07 1.952-4.242 0-1.172-1.953-1.172-5.119 0-7.072 1.171-1.952 3.07-1.952 4.242 0M8 10.5h4m-4 3h4m9-1.5a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">Produksi</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @if($hasPerm('production-list') || $isSuperAdmin)
        <a href="{{ route('produksi.index') }}" x-show="navLinkVisible('Production', 'Produksi')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'produksi' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Production</a>
        @endif
        @if($hasPerm('production-setoran-list') || $isSuperAdmin)
        <a href="{{ route('produksi.setoran.index') }}" x-show="navLinkVisible('Setoran', 'Produksi')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/produksi/setoran') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Setoran</a>
        @endif
        @if($hasPerm('production-worker-list') || $isSuperAdmin)
        <a href="{{ route('produksi.potong.index') }}" x-show="navLinkVisible('Potong Workers', 'Produksi')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/produksi/potong') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Potong Workers</a>
        <a href="{{ route('produksi.jahit.index') }}" x-show="navLinkVisible('Jahit Workers', 'Produksi')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/produksi/jahit') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Jahit Workers</a>
        <a href="{{ route('produksi.qc.index') }}" x-show="navLinkVisible('QC Workers', 'Produksi')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/produksi/qc') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">QC Workers</a>
        <a href="{{ route('produksi.pritil.index') }}" x-show="navLinkVisible('Pritil Workers', 'Produksi')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/produksi/pritil') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Pritil Workers</a>
        @endif
    </div>
</div>
@endif

{{-- ── Borongan ──────────────────────────────────────────────────────── --}}
@if($hasPerm('borongan-list') || $isSuperAdmin)
<div class="mb-1" x-show="navLinkVisible('Borongan')">
    <a href="{{ route('borongan.index') }}"
       class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
              {{ $isActive('/borongan') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        <span x-show="sidebarOpen" x-cloak>Borongan</span>
    </a>
</div>
@endif

{{-- ── Archive (read-only) ───────────────────────────────────────────── --}}
@if($hasPerm('archive-view') || $isSuperAdmin)
@php $archiveActive = $isActive('/archive'); @endphp
<div x-data="{ open: {{ $archiveActive ? 'true' : 'false' }} }"
     class="mb-1"
     x-show="navGroupVisible(@js($archiveNavLabels))"
     x-effect="syncNavGroupOpen($data, {{ $archiveActive ? 'true' : 'false' }}, @js($archiveNavLabels))">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $archiveActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">Archive</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        <a href="{{ route('archive.index') }}" x-show="navLinkVisible('Overview', 'Archive')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $currentUrl === 'archive' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Overview</a>
        <a href="{{ route('archive.transactions.index') }}" x-show="navLinkVisible('Transactions', 'Archive')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/archive/transactions') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Transactions</a>
        <a href="{{ route('archive.items.index') }}" x-show="navLinkVisible('Items', 'Archive')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/archive/items') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Items</a>
    </div>
</div>
@endif

{{-- ── System Settings ───────────────────────────────────────────────── --}}
@if($hasPerm('setting-general-view') || $hasPerm('setting-cron-manager-view') || $isSuperAdmin)
@php $sysActive = $isActive('/system-settings') || $isActive('/cron-manager') || $isActive('/data-retention'); @endphp
<div x-data="{ open: {{ $sysActive ? 'true' : 'false' }} }"
     class="mb-1"
     x-show="navGroupVisible(@js($systemNavLabels))"
     x-effect="syncNavGroupOpen($data, {{ $sysActive ? 'true' : 'false' }}, @js($systemNavLabels))">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $sysActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">System Settings</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @if($hasPerm('setting-general-view') || $isSuperAdmin)
        <a href="{{ route('system-settings.index') }}" x-show="navLinkVisible('General', 'System Settings')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/system-settings') && ! $isActive('/cron-manager') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">General</a>
        @endif
        @if($hasPerm('setting-cron-manager-view') || $isSuperAdmin)
        <a href="{{ route('scheduled-tasks.index') }}" x-show="navLinkVisible('Cron Manager', 'System Settings')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/cron-manager') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Cron Manager</a>
        @endif
        @if($hasPerm('report-warehouse-arrangement') || $isSuperAdmin)
        <a href="{{ route('warehouse-stat-backfill.index') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/warehouse-stat-backfill') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Warehouse Stats Backfill</a>
        @endif
        @if($isSuperAdmin)
        <a href="{{ route('data-retention.index') }}" x-show="navLinkVisible('Data Retention', 'System Settings')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/data-retention') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Data Retention</a>
        <a href="{{ route('data-retention.item-purge.index') }}" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/data-retention/item-purge') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Selective Item Purge</a>
        @endif
    </div>
</div>
@endif

{{-- ── HR / Payroll ──────────────────────────────────────────────────── --}}
@if($hasPerm('karyawan-list') || $hasPerm('karyawan-gaji-list') || $isSuperAdmin)
@php $hrActive = $isActive('/karyawan') || $isActive('/gaji'); @endphp
<div x-data="{ open: {{ $hrActive ? 'true' : 'false' }} }"
     class="mb-1"
     x-show="navGroupVisible(@js($hrNavLabels))"
     x-effect="syncNavGroupOpen($data, {{ $hrActive ? 'true' : 'false' }}, @js($hrNavLabels))">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $hrActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">HR / Payroll</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @if($hasPerm('karyawan-list') || $isSuperAdmin)
        <a href="{{ route('karyawan.index') }}" x-show="navLinkVisible('Karyawan', 'HR / Payroll')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/karyawan') && ! $isActive('/gaji') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Karyawan</a>
        @endif
        @if($hasPerm('karyawan-gaji-list') || $isSuperAdmin)
        <a href="{{ route('gaji.index') }}" x-show="navLinkVisible('Gaji', 'HR / Payroll')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/gaji') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Gaji</a>
        @endif
    </div>
</div>
@endif

{{-- ── User Management ───────────────────────────────────────────────── --}}
@if($hasPerm('users-list') || $hasPerm('users-roles-list') || $isSuperAdmin)
@php $umActive = $isActive('/users') || $isActive('/roles') || $isActive('/permissions'); @endphp
<div x-data="{ open: {{ $umActive ? 'true' : 'false' }} }"
     class="mb-1"
     x-show="navGroupVisible(@js($userNavLabels))"
     x-effect="syncNavGroupOpen($data, {{ $umActive ? 'true' : 'false' }}, @js($userNavLabels))">
    <button @click="open = !open"
            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors
                   {{ $umActive ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
        <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <span x-show="sidebarOpen" x-cloak class="flex-1 text-left">User Management</span>
        <svg x-show="sidebarOpen" x-cloak :class="open ? 'rotate-90' : ''" class="h-3.5 w-3.5 flex-shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open && sidebarOpen" x-cloak class="ml-6 mt-1 space-y-0.5">
        @if($hasPerm('users-list') || $isSuperAdmin)
        <a href="{{ route('users.index') }}" x-show="navLinkVisible('Users', 'User Management')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/users') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Users</a>
        @endif
        @if($hasPerm('users-roles-list') || $isSuperAdmin)
        <a href="{{ route('roles.index') }}" x-show="navLinkVisible('Roles', 'User Management')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/roles') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Roles</a>
        @endif
        @if($hasPerm('users-permissions-list') || $isSuperAdmin)
        <a href="{{ route('permissions.index') }}" x-show="navLinkVisible('Permissions', 'User Management')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/permissions') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Permissions</a>
        @endif
        @if($hasPerm('users-locations-list') || $isSuperAdmin)
        <a href="{{ route('locations.index') }}" x-show="navLinkVisible('Locations', 'User Management')" class="block rounded-md px-2.5 py-1.5 text-sm {{ $isActive('/locations') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">Locations</a>
        @endif
    </div>
</div>
@endif
