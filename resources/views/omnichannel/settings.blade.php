@extends('layouts.app')

@section('title', 'Omnichannel — Settings')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Omnichannel', 'href' => route('omnichannel.settings.index')],
    ['title' => 'Settings', 'href' => route('omnichannel.settings.index')],
];
$tabs = [
    'connections' => 'Connections',
    'warehouse' => 'Warehouse mapping',
    'low-stock' => 'Low stock (Shopee)',
];
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4" data-testid="omnichannel-settings-page">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Omnichannel — Settings</h1>
        <p class="mt-1 text-sm text-gray-600">Konfigurasi saluran, mapping gudang, dan kebijakan stok rendah (read-only / stored — tanpa push stok otomatis di v1).</p>
    </div>

    @if(($flash['success'] ?? null))
    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ $flash['success'] }}</div>
    @endif
    @if(($flash['error'] ?? null))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-2" data-testid="omnichannel-settings-tabs">
        @foreach($tabs as $tabKey => $tabLabel)
        <a href="{{ route('omnichannel.settings.index', ['tab' => $tabKey]) }}"
           class="rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $tab === $tabKey ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
            {{ $tabLabel }}
        </a>
        @endforeach
    </div>

    @if($tab === 'connections')
    <div class="grid gap-4 md:grid-cols-3" data-testid="omnichannel-settings-connections">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500">Shopee Admin OAuth</p>
            <p class="mt-2 text-lg font-semibold {{ ($shopeeAdminConnection['has_token'] ?? false) && !($shopeeAdminConnection['is_expired'] ?? true) ? 'text-green-700' : 'text-red-700' }}">
                {{ ($shopeeAdminConnection['has_token'] ?? false) ? (($shopeeAdminConnection['is_expired'] ?? false) ? 'Kedaluwarsa' : 'Terhubung') : 'Belum authorize' }}
            </p>
            <p class="mt-1 text-xs text-gray-500">Partner / push config: {{ $shopeePushConfigured ? 'OK' : 'belum' }}</p>
            @php
                $shopeeAuthorizedIds = $shopeeAdminConnection['authorized_shop_ids'] ?? [];
                $shopeeIdList = $shopeeAdminConnection['shop_id_list'] ?? [];
            @endphp
            @if($shopeeAuthorizedIds !== [])
            <p class="mt-2 text-xs text-gray-600">
                Shop ID terotorisasi:
                <span class="font-mono">{{ implode(', ', array_map('strval', $shopeeAuthorizedIds)) }}</span>
            </p>
            @endif
            @if(is_array($shopeeIdList) && $shopeeIdList !== [] && count($shopeeIdList) !== count($shopeeAuthorizedIds))
            <p class="mt-1 text-xs text-gray-500">shop_id_list (authorize terakhir): <span class="font-mono">{{ implode(', ', array_map('strval', $shopeeIdList)) }}</span></p>
            @endif
            @if(filled($shopeeAdminConnection['main_account_id'] ?? null))
            <p class="mt-1 text-xs text-gray-500">Main account: <span class="font-mono">{{ $shopeeAdminConnection['main_account_id'] }}</span></p>
            @endif
            @if(filled($shopeeAdminConnection['last_oauth_callback_at'] ?? null))
            <p class="mt-1 text-xs text-gray-500">Callback terakhir {{ $shopeeAdminConnection['last_oauth_callback_at'] }}
                @if(is_array($shopeeAdminConnection['last_oauth_callback_query'] ?? null))
                · query: <code class="text-[10px]">{{ json_encode($shopeeAdminConnection['last_oauth_callback_query']) }}</code>
                @endif
            </p>
            @endif
            <p class="mt-1 text-xs text-amber-800">Direct Shop (MY): authorize lewat <strong>Main account</strong> — pilih Main + Direct; ID muncul di sini setelah redirect.</p>
            <a href="{{ route('shopee-admin.authorize', ['return' => 'omnichannel']) }}"
               class="mt-3 inline-flex rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Reconnect Shopee Admin</a>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500">Shopee Stock OAuth</p>
            <p class="mt-2 text-lg font-semibold {{ ($shopeeStockConnection['has_token'] ?? false) ? 'text-green-700' : 'text-gray-600' }}">
                {{ ($shopeeStockConnection['configured'] ?? false) ? 'Configured' : 'Env belum lengkap' }}
            </p>
            @if($canShopeeSync)
            <a href="{{ route('shopee.sync.authorize') }}"
               class="mt-3 inline-flex rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Reconnect stock shop</a>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500">TikTok Shop OAuth</p>
            <p class="mt-2 text-lg font-semibold {{ ($tiktokOAuthConnection['has_token'] ?? false) && !($tiktokOAuthConnection['is_expired'] ?? true) ? 'text-green-700' : 'text-red-700' }}">
                {{ ($tiktokOAuthConnection['has_token'] ?? false) ? (($tiktokOAuthConnection['is_expired'] ?? false) ? 'Kedaluwarsa' : 'Terhubung') : 'Belum authorize' }}
            </p>
            <p class="mt-1 text-xs text-gray-500">App key: {{ $tiktokAppConfigured ? 'OK' : 'belum' }} · Webhook inbox: {{ $tiktokWebhookInbox ? 'on' : 'off' }}</p>
            <a href="{{ route('tiktok-shop.authorize', ['return' => 'omnichannel']) }}"
               class="mt-3 inline-flex rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
               data-testid="tiktok-shop-authorize-button">Authorize TikTok Shop</a>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-gray-500">Tokopedia storefront</p>
            <p class="mt-2 text-sm text-gray-600">TikTok Partner webhooks (Orders). Mapping gudang di tab Warehouse; Jubelio tetap untuk hub terpusat.</p>
            <a href="{{ route('omnichannel.settings.index', ['tab' => 'warehouse']) }}"
               class="mt-3 inline-flex rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Buka warehouse mapping</a>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:col-span-2">
            <p class="text-xs font-semibold uppercase text-gray-500">Jubelio API</p>
            <p class="mt-2 text-lg font-semibold {{ ($jubelioConnection['has_token'] ?? false) && !($jubelioConnection['is_expired'] ?? true) ? 'text-green-700' : 'text-red-700' }}">
                {{ $jubelioActive ? 'Aktif' : 'Nonaktif (env)' }} ·
                {{ ($jubelioConnection['has_token'] ?? false) ? 'Token ada' : 'Tanpa token' }}
            </p>
            <a href="{{ route('jubelio.token.index') }}"
               class="mt-3 inline-flex rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Buka halaman Koneksi Jubelio</a>
        </div>
    </div>
    @elseif($tab === 'warehouse')
    <div class="space-y-6" data-testid="omnichannel-settings-warehouse">
        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-900">Jubelio warehouse map</h2>
                @if($canJubelioSync)
                <a href="{{ route('jubelio.sync.index') }}" class="text-xs font-medium text-blue-700 hover:underline">Kelola penuh →</a>
                @else
                <span class="text-xs text-gray-500">Perlu permission <code>jubelio-sync</code></span>
                @endif
            </div>
            @if(!$canJubelioSync)
            <p class="mt-3 text-sm text-gray-500">Anda tidak memiliki akses edit mapping Jubelio.</p>
            @elseif($jubelioSyncRows->isEmpty())
            <p class="mt-3 text-sm text-gray-500">Belum ada baris jubeliosyncs.</p>
            @else
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-100 text-[10px] uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pr-2">Lokasi Jubelio</th>
                            <th class="py-2 pr-2">Gudang Aria</th>
                            <th class="py-2">Customer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($jubelioSyncRows as $sync)
                        <tr>
                            <td class="py-2 pr-2">{{ $sync->jubelio_location_name }} ({{ $sync->jubelio_location_id }})</td>
                            <td class="py-2 pr-2">{{ $sync->warehouse->name ?? $sync->warehouse_id }}</td>
                            <td class="py-2">{{ $sync->customer->name ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-900">Shopee warehouse map</h2>
                @if($canShopeeSync)
                <span class="flex gap-3 text-xs font-medium">
                    <a href="{{ route('shopee.flexi.index') }}" class="text-orange-700 hover:underline">FLEXI shops (shop_id → prefix)</a>
                    <a href="{{ route('shopee.sync.index') }}" class="text-blue-700 hover:underline">Warehouse map →</a>
                </span>
                @else
                <span class="text-xs text-gray-500">Perlu permission <code>shopee-stock-sync</code></span>
                @endif
            </div>
            @if(!$canShopeeSync)
            <p class="mt-3 text-sm text-gray-500">Anda tidak memiliki akses edit mapping Shopee.</p>
            @elseif($shopeeSyncRows->isEmpty())
            <p class="mt-3 text-sm text-gray-500">Belum ada baris shopee_syncs.</p>
            @else
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-100 text-[10px] uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pr-2">Shopee WH</th>
                            <th class="py-2">Gudang Aria</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($shopeeSyncRows as $sync)
                        <tr>
                            <td class="py-2 pr-2">{{ $sync->shopee_warehouse_name ?? $sync->shopee_location_id }}</td>
                            <td class="py-2">{{ $sync->warehouse->name ?? $sync->warehouse_id }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>

        <section id="omnichannel-settings-flexi-staging" class="rounded-xl border border-orange-200 bg-orange-50/30 p-4 shadow-sm" data-testid="omnichannel-settings-flexi-staging">
            <h2 class="text-sm font-semibold text-gray-900">Shopee FLEXI — gudang staging (shared)</h2>
            <p class="mt-2 text-sm text-gray-600">
                Satu gudang fisik untuk alur MOVE → SELL semua shop FLEXI Malaysia/export. Disimpan di <code class="text-xs">omnichannel_settings</code>
                (bukan env). Env <code class="text-xs">SHOPEE_FLEXI_STAGING_WAREHOUSE_ID</code> hanya override darurat bila DB kosong.
            </p>
            @if(! $settingsTableExists)
            <p class="mt-4 text-sm text-amber-800">Migrasi <code>omnichannel_settings</code> belum dijalankan.</p>
            @else
            <form method="post" action="{{ route('omnichannel.settings.flexi-staging.update') }}" class="mt-4 space-y-3 rounded-lg border border-gray-200 bg-white p-3">
                @csrf
                <div>
                    <label for="flexi_staging_warehouse_id" class="mb-1 block text-xs font-medium text-gray-600">Gudang staging</label>
                    <select name="flexi_staging_warehouse_id" id="flexi_staging_warehouse_id"
                            class="w-full max-w-md rounded-md border-gray-300 text-sm"
                            @disabled(! $canEditLowStock)>
                        <option value="0" @selected((int) old('flexi_staging_warehouse_id', $flexiStagingWarehouseId) === 0)>— Belum dipilih —</option>
                        @foreach($physicalWarehouses as $wh)
                        <option value="{{ $wh->id }}"
                                @selected((int) old('flexi_staging_warehouse_id', $flexiStagingWarehouseId) === (int) $wh->id)>
                            #{{ $wh->id }} — {{ $wh->name }}
                            @if($wh->arrangement_enabled)
                            (arrangement)
                            @endif
                        </option>
                        @endforeach
                    </select>
                    @error('flexi_staging_warehouse_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                @if($canEditLowStock)
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800" data-testid="omnichannel-flexi-staging-save">Simpan gudang staging FLEXI</button>
                @else
                <p class="text-xs text-gray-500">Perlu permission <code>omnichannel-settings-edit</code> atau superadmin.</p>
                @endif
            </form>
            @endif
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-900">TikTok Shop / Tokopedia (Partner)</h2>
                @if($canTiktokSync)
                <a href="{{ route('tiktok-shop.sync.index') }}" class="text-xs font-medium text-blue-700 hover:underline">Kelola penuh →</a>
                @else
                <span class="text-xs text-gray-500">Perlu <code>tiktok-shop-sync</code> atau <code>omnichannel-settings-edit</code></span>
                @endif
            </div>
            <p class="mt-2 text-sm text-gray-600">
                Petakan <code class="text-xs">shop_id</code> webhook TikTok ke gudang Aria. Tokopedia storefront di Orders memakai vocabulary yang sama; filter channel <strong>Tokopedia (TikTok Partner)</strong>.
            </p>
            @if(!$canTiktokSync)
            <p class="mt-3 text-sm text-gray-500">Anda tidak memiliki akses edit mapping TikTok.</p>
            @elseif($tiktokSyncRows->isEmpty())
            <p class="mt-3 text-sm text-gray-500">Belum ada baris <code>tiktok_shop_syncs</code>.</p>
            @else
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-100 text-[10px] uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pr-2">shop_ref</th>
                            <th class="py-2 pr-2">Nama</th>
                            <th class="py-2">Gudang Aria</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($tiktokSyncRows as $sync)
                        <tr>
                            <td class="py-2 pr-2 font-mono text-[11px]">{{ $sync->shop_ref }}</td>
                            <td class="py-2 pr-2">{{ $sync->shop_name ?: '—' }}</td>
                            <td class="py-2">{{ $sync->warehouse->name ?? $sync->warehouse_id }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>

        <section id="tokopedia-via-jubelio" class="rounded-xl border border-amber-200 bg-amber-50/40 p-4 shadow-sm" data-testid="omnichannel-settings-tokopedia">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-900">Tokopedia / channel lain (via Jubelio)</h2>
                @if($canJubelioSync)
                <a href="{{ route('jubelio.sync.index', ['store' => 'tokopedia']) }}"
                   class="text-xs font-medium text-blue-700 hover:underline">Jubelio warehouse map → Tokopedia</a>
                @endif
            </div>
            <p class="mt-2 text-sm text-gray-600">
                Order hub Jubelio (bukan storefront Partner di atas). Pastikan baris <code class="text-xs">jubeliosyncs</code> dengan nama store mengandung &quot;Tokopedia&quot;
                terpetakan ke gudang + customer yang benar (resi scan: <code class="text-xs">config/jubelio_resi_scan.php</code>).
            </p>
            @if($tokopediaSyncRows->isNotEmpty())
            <div class="mt-3 overflow-x-auto rounded-lg border border-amber-100 bg-white">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-100 text-[10px] uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pl-3 pr-2">Jubelio store</th>
                            <th class="py-2 pr-2">store_id</th>
                            <th class="py-2 pr-2">Lokasi</th>
                            <th class="py-2 pr-2">Gudang Aria</th>
                            <th class="py-2 pr-3">Customer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($tokopediaSyncRows as $sync)
                        <tr class="bg-amber-50/30">
                            <td class="py-2 pl-3 pr-2 font-medium">{{ $sync->jubelio_store_name }}</td>
                            <td class="py-2 pr-2 font-mono text-[11px]">{{ $sync->jubelio_store_id }}</td>
                            <td class="py-2 pr-2">{{ $sync->jubelio_location_name }}</td>
                            <td class="py-2 pr-2">{{ $sync->warehouse->name ?? $sync->warehouse_id }}</td>
                            <td class="py-2 pr-3">{{ $sync->customer->name ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="mt-3 text-sm text-gray-500">Belum ada baris jubeliosyncs yang namanya mengandung &quot;Tokopedia&quot;.</p>
            @endif

            @if($settingsTableExists)
            <form method="post" action="{{ route('omnichannel.settings.tokopedia.update') }}" class="mt-4 space-y-3 rounded-lg border border-gray-200 bg-white p-3">
                @csrf
                <p class="text-[10px] font-semibold uppercase text-gray-500">Checklist maintainer (opsional)</p>
                <div>
                    <label for="tokopedia_store_id" class="mb-1 block text-xs font-medium text-gray-600">Tokopedia store id (referensi)</label>
                    <input type="text" name="tokopedia_store_id" id="tokopedia_store_id" maxlength="64"
                           value="{{ old('tokopedia_store_id', $tokopediaStoreId) }}"
                           class="w-full max-w-md rounded-md border-gray-300 text-sm"
                           @disabled(! $canEditLowStock)>
                </div>
                <div>
                    <label for="tokopedia_notes" class="mb-1 block text-xs font-medium text-gray-600">Catatan</label>
                    <textarea name="tokopedia_notes" id="tokopedia_notes" rows="3" maxlength="2000"
                              class="w-full rounded-md border-gray-300 text-sm"
                              @disabled(! $canEditLowStock)>{{ old('tokopedia_notes', $tokopediaNotes) }}</textarea>
                </div>
                @if($canEditLowStock)
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">Simpan catatan Tokopedia</button>
                @else
                <p class="text-xs text-gray-500">Perlu permission <code>omnichannel-settings-edit</code> atau superadmin.</p>
                @endif
            </form>
            @endif
        </section>
    </div>
    @elseif($tab === 'low-stock')
    <div class="max-w-lg rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="omnichannel-settings-low-stock">
        <h2 class="text-sm font-semibold text-gray-900">Kebijakan stok rendah Shopee (v1)</h2>
        <p class="mt-2 text-sm text-gray-600">
            Simpan threshold <strong>X</strong>: bila qty listing Shopee &lt; X, kebijakan ini berlaku untuk runbook ops.
            <strong>Tidak ada push stok otomatis</strong> dari halaman ini (hanya penyimpanan threshold).
        </p>
        @if(! $settingsTableExists)
        <p class="mt-4 text-sm text-amber-800">Migrasi <code>omnichannel_settings</code> belum dijalankan.</p>
        @else
        <form method="post" action="{{ route('omnichannel.settings.low-stock.update') }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label for="shopee_low_stock_threshold" class="mb-1 block text-xs font-medium text-gray-600">Threshold X (integer ≥ 0)</label>
                <input type="number" name="shopee_low_stock_threshold" id="shopee_low_stock_threshold" min="0" max="999999"
                       value="{{ old('shopee_low_stock_threshold', $lowStockThreshold) }}"
                       class="w-full rounded-md border-gray-300 text-sm"
                       @disabled(! $canEditLowStock)>
                @error('shopee_low_stock_threshold')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            @if($canEditLowStock)
            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800" data-testid="omnichannel-low-stock-save">Simpan</button>
            @else
            <p class="text-xs text-gray-500">Perlu permission <code>omnichannel-settings-edit</code> atau superadmin.</p>
            @endif
        </form>
        @endif
    </div>
    @endif
</div>
@endsection