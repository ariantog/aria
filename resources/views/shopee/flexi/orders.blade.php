@extends('layouts.app')

@section('title', 'FLEXI Export Orders')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'FLEXI Shops', 'href' => route('shopee.flexi.index')],
    ['title' => 'Export orders', 'href' => route('shopee.flexi.orders')],
];
$loadDetailChecked = (bool) old('load_detail', $loadDetail ?? false);
@endphp

<div class="flex flex-col gap-6 p-4" data-testid="shopee-flexi-orders">
    <div>
        <h1 class="text-2xl font-bold">Order export FLEXI</h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500">
            Alur staging → SELL untuk toko Malaysia / export. Gudang staging:
            @if(($stagingWarehouse['valid'] ?? false))
                <strong class="font-medium text-gray-800" data-testid="flexi-staging-name">#{{ $stagingWarehouse['warehouse_id'] }} {{ $stagingWarehouse['warehouse_name'] }}</strong>
            @else
                <span class="text-amber-800" data-testid="flexi-staging-unset">belum dikonfigurasi</span>
            @endif
            — atur di <a href="{{ route('omnichannel.settings.index', ['tab' => 'warehouse']) }}#omnichannel-settings-flexi-staging" class="text-orange-700 hover:underline">Omnichannel → Settings → Warehouse</a>).
        </p>
        <ol class="mt-3 max-w-3xl list-decimal space-y-1 pl-5 text-sm text-gray-600">
            <li>Cari order (ordersn / invoice prefix)</li>
            <li>Muat detail dari Shopee API</li>
            <li><strong class="font-medium text-gray-800">Pindah ke gudang FLEXI</strong> — draft MOVE pickup → staging</li>
            <li>Tunggu status <span class="font-mono text-xs">COMPLETED</span> + escrow (<code>get_escrow_detail</code>)</li>
            <li><strong class="font-medium text-gray-800">Buat Transaksi Manual</strong> — SELL dari staging (bila dikonfigurasi)</li>
        </ol>
        @if(filled($stagingWarehouse['warning'] ?? null))
            <div class="mt-3 max-w-3xl rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="flexi-staging-warning">
                {{ $stagingWarehouse['warning'] }}
            </div>
        @endif
        <details class="mt-3 max-w-3xl rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
            <summary class="cursor-pointer font-medium text-gray-900">Catatan FLEXI</summary>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-gray-600">
                <li>Jubelio tidak mengirim payload order export — <strong>tidak ada antrean Jubelio</strong> untuk shop FLEXI.</li>
                <li>Poll opsional (<code>shopee:poll-orders</code>) hanya untuk shadow / omnichannel events, bukan posting otomatis.</li>
                <li>Mapping <code>shop_id</code> + prefix invoice dikelola di <a href="{{ route('shopee.flexi.index') }}" class="text-orange-700 underline">FLEXI Shops</a>; gudang via <a href="{{ route('shopee.sync.index') }}" class="text-orange-700 underline">Warehouse Map</a>.</li>
            </ul>
        </details>
    </div>

    @if(filled($flash['success'] ?? null))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">{{ $flash['success'] }}</div>
    @endif
    @if(filled($flash['error'] ?? null))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" data-testid="shopee-orders-flash-error">{{ $flash['error'] }}</div>
    @endif

    @if(! empty($authorizedShopIds))
        <p class="text-xs text-gray-600">
            OAuth shop IDs: <span class="font-mono" data-testid="shopee-oauth-shop-ids">{{ implode(', ', array_map('strval', $authorizedShopIds)) }}</span>
        </p>
    @else
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Belum ada shop di OAuth — authorize lewat <a href="{{ route('shopee-admin.authorize') }}" class="font-medium underline">Shopee Admin</a> dulu.
        </div>
    @endif

    <form method="POST" action="{{ route('shopee.flexi.orders.search') }}" class="max-w-xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        <div>
            <label for="order_ref" class="block text-sm font-medium text-gray-700">Order ref</label>
            <input type="text" name="order_ref" id="order_ref" value="{{ $orderRef }}" required
                placeholder="ordersn atau SPMY-240101ABC"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
            <p class="mt-1 text-xs text-gray-500">Ordersn mentah atau invoice ber-prefix (<code>SPMY-…</code>) — prefix akan di-strip.</p>
            @error('order_ref') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="shop_id" class="block text-sm font-medium text-gray-700">Shop ID</label>
            @if(($shopOptions ?? []) === [])
                <p class="mt-1 text-sm text-amber-800">Belum ada shop di <a href="{{ route('shopee.flexi.index') }}" class="underline">FLEXI shops</a> atau OAuth — authorize Shopee Admin dulu.</p>
                <input type="hidden" name="shop_id" value="">
            @else
                <select name="shop_id" id="shop_id" required
                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm focus:border-orange-500 focus:ring-1 focus:ring-orange-500"
                    data-testid="shopee-orders-shop-id">
                    <option value="" disabled @selected($shopId === '')>Pilih shop…</option>
                    @foreach($shopOptions as $option)
                        <option value="{{ $option['id'] }}" @selected((string) $option['id'] === (string) $shopId)>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            @endif
            @error('shop_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-start gap-2">
            <input type="checkbox" name="load_detail" id="load_detail" value="1"
                @checked($loadDetailChecked)
                class="mt-1 rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                data-testid="shopee-orders-load-detail">
            <label for="load_detail" class="text-sm text-gray-700">
                Muat detail dari Shopee API
                <span class="block text-xs font-normal text-gray-500">Centang untuk status, item, gudang, dan tombol Buat Transaksi Manual.</span>
            </label>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700" data-testid="shopee-orders-submit">
                Cari order
            </button>
            <a href="{{ route('shopee.flexi.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">FLEXI shops</a>
        </div>
    </form>

    @if($result !== null)
        <div class="space-y-4" data-testid="shopee-orders-result">
            @if(! ($result['ok'] ?? false))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" data-testid="shopee-orders-error">
                    <p>{{ $result['error'] ?? 'Lookup gagal.' }}</p>
                    @if(filled($result['error_detail'] ?? null) && ($result['error_detail'] ?? '') !== ($result['error'] ?? ''))
                        <p class="mt-2 font-mono text-xs text-red-900/90">{{ $result['error_detail'] }}</p>
                    @endif
                </div>
            @else
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                    @if($result['detail_loaded'] ?? false)
                        Order ditemukan — shop_id <span class="font-mono font-semibold" data-testid="result-shop-id">{{ $result['shop_id'] }}</span>,
                        ordersn <span class="font-mono">{{ $result['order_sn'] }}</span>
                    @else
                        Mapping dasar — shop_id <span class="font-mono font-semibold" data-testid="result-shop-id">{{ $result['shop_id'] }}</span>,
                        invoice <span class="font-mono">{{ $result['invoice_preview'] ?? '—' }}</span>
                        <span class="block mt-1 text-xs text-green-800">Detail API belum dimuat.</span>
                    @endif
                </div>
            @endif

            @if(($result['ok'] ?? false) && ! ($result['detail_loaded'] ?? false))
                <form method="POST" action="{{ route('shopee.flexi.orders.search') }}" class="inline">
                    @csrf
                    <input type="hidden" name="order_ref" value="{{ $orderRef }}">
                    <input type="hidden" name="shop_id" value="{{ $shopId }}">
                    <input type="hidden" name="load_detail" value="1">
                    <button type="submit" class="rounded-lg border border-orange-300 bg-orange-50 px-4 py-2 text-sm font-medium text-orange-900 hover:bg-orange-100" data-testid="shopee-load-detail-btn">
                        Muat detail
                    </button>
                </form>
            @endif

            @if(($result['invoice_exists'] ?? false) && filled($result['existing_transaction_id'] ?? null))
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="shopee-invoice-exists">
                    Invoice sudah diposting —
                    <a href="{{ route('transactions.show', $result['existing_transaction_id']) }}" class="font-medium text-orange-800 underline">lihat transaksi</a>.
                </div>
            @else
                @if(($result['detail_loaded'] ?? false) && ($result['ok'] ?? false))
                    @if($result['can_move_to_staging'] ?? false)
                        <form method="POST" action="{{ route('shopee.flexi.orders.move-to-staging') }}" onsubmit="return confirm('Buka form MOVE pickup → gudang FLEXI staging?')" class="inline">
                            @csrf
                            <input type="hidden" name="order_ref" value="{{ $orderRef }}">
                            <input type="hidden" name="shop_id" value="{{ $shopId }}">
                            <button type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800"
                                data-testid="shopee-flexi-move-to-staging">
                                Pindah ke gudang FLEXI
                            </button>
                        </form>
                    @elseif(filled($result['move_to_staging_block'] ?? null))
                        <p class="text-sm text-amber-800" data-testid="shopee-move-to-staging-block">{{ $result['move_to_staging_block'] }}</p>
                    @endif
                @endif

                @if(($result['can_manual_sell'] ?? false))
                @php
                    $priceSource = $result['amount_resolution']['price_source'] ?? null;
                    $priceSourceLabel = match ($priceSource) {
                        'escrow_fx' => 'Harga prefill: kurs escrow Shopee (IDR)',
                        'escrow_idr' => 'Harga prefill: penghasilan IDR dari escrow (tanpa kurs)',
                        'catalog_fallback' => 'Harga prefill: katalog Aria (escrow IDR/kurs tidak ditemukan)',
                        'shopee_idr' => 'Harga prefill: nominal Shopee (IDR)',
                        default => null,
                    };
                @endphp
                @if(filled($result['amount_resolution']['banner'] ?? null))
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="shopee-flexi-amount-banner">
                        {{ $result['amount_resolution']['banner'] }}
                    </div>
                @endif
                @if(filled($priceSourceLabel))
                    <p class="text-sm text-gray-700" data-testid="shopee-flexi-price-source">{{ $priceSourceLabel }}</p>
                @endif
                <form method="POST" action="{{ route('shopee.flexi.orders.create-sell') }}" onsubmit="return confirm('Buka form SELL dengan data order ini?')" class="inline">
                    @csrf
                    <input type="hidden" name="order_ref" value="{{ $orderRef }}">
                    <input type="hidden" name="shop_id" value="{{ $shopId }}">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800"
                        data-testid="shopee-flexi-create-sell">
                        Buat Transaksi Manual
                    </button>
                </form>
                @elseif(($result['detail_loaded'] ?? false) && ($result['ok'] ?? false) && filled($result['manual_sell_block'] ?? null))
                    <p class="text-sm text-amber-800" data-testid="shopee-manual-sell-block">{{ $result['manual_sell_block'] }}</p>
                @endif
            @endif

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-gray-100">
                        <tr><th class="w-48 bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">shop_id</th>
                            <td class="px-4 py-3 font-mono" data-testid="result-shop-id-cell">{{ $result['shop_id'] ?? '—' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">OAuth shop</th>
                            <td class="px-4 py-3">{{ ($result['shop_authorized'] ?? false) ? 'Ya' : 'Tidak' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Detail API</th>
                            <td class="px-4 py-3" data-testid="result-detail-loaded">{{ ($result['detail_loaded'] ?? false) ? 'Dimuat' : 'Belum' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Region hint</th>
                            <td class="px-4 py-3">{{ $result['region_hint'] ?? '—' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Invoice prefix</th>
                            <td class="px-4 py-3 font-mono">{{ $result['invoice_prefix'] ?? '—' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Invoice preview</th>
                            <td class="px-4 py-3 font-mono">{{ $result['invoice_preview'] ?? '—' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Sudah di transaksi</th>
                            <td class="px-4 py-3" data-testid="result-invoice-exists">{{ ($result['invoice_exists'] ?? false) ? 'Ya' : 'Tidak' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">FLEXI row</th>
                            <td class="px-4 py-3 text-gray-600">
                                @if(! empty($result['flexi_mapping']))
                                    <span class="font-mono">{{ $result['flexi_mapping']['invoice_prefix'] }}</span>
                                    @if($result['flexi_mapping']['direct_order_ingest'] ?? false) · poll shadow @endif
                                    @if(filled($result['flexi_mapping']['label'] ?? '')) · {{ $result['flexi_mapping']['label'] }} @endif
                                @else
                                    <span class="text-gray-400">Belum di /shopee-flexi — preview prefix dari config/sync legacy.</span>
                                @endif
                            </td></tr>
                        @if($result['detail_loaded'] ?? false)
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Mata uang Shopee</th>
                            <td class="px-4 py-3 font-mono" data-testid="result-order-currency">{{ $result['order_currency'] ?? '—' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Total Shopee</th>
                            <td class="px-4 py-3 font-mono" data-testid="result-order-total">
                                @if(filled($result['order_total_original'] ?? null))
                                    {{ $result['order_currency'] ?? '' }} {{ number_format((float) $result['order_total_original'], 2, '.', ',') }}
                                @else
                                    —
                                @endif
                            </td></tr>
                        @if(! empty($result['amount_resolution']['escrow_fetch'] ?? null))
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Sumber escrow API</th>
                            <td class="px-4 py-3 font-mono text-xs" data-testid="result-escrow-fetch">
                                shop_id {{ $result['amount_resolution']['escrow_fetch']['shop_id'] ?? '—' }}
                                · order_sn {{ $result['amount_resolution']['escrow_fetch']['order_sn'] ?? '—' }}
                                @if($result['amount_resolution']['escrow_fetch']['via_primary_shop'] ?? false)
                                    <span class="text-blue-800">· primary ID shop</span>
                                @endif
                                @if(filled($result['amount_resolution']['idr_escrow_total'] ?? null))
                                    <span class="block text-green-800">IDR escrow: Rp{{ number_format((float) $result['amount_resolution']['idr_escrow_total'], 0, '.', ',') }}</span>
                                @endif
                            </td></tr>
                        @endif
                        @if(! empty($result['escrow_snippet']))
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Escrow</th>
                            <td class="px-4 py-3 font-mono text-xs" data-testid="result-escrow-snippet">
                                @foreach($result['escrow_snippet'] as $ek => $ev)
                                    <span class="block">{{ $ek }}: {{ is_scalar($ev) ? $ev : json_encode($ev) }}</span>
                                @endforeach
                            </td></tr>
                        @endif
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Order status</th>
                            <td class="px-4 py-3 font-mono" data-testid="result-order-status">{{ $result['order_status'] ?? '—' }}</td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Pickup</th>
                            <td class="px-4 py-3 font-mono text-xs">
                                @if(! empty($result['pickup']))
                                    loc: {{ $result['pickup']['shopee_location_id'] !== '' ? $result['pickup']['shopee_location_id'] : '—' }}
                                    · wh: {{ $result['pickup']['shopee_warehouse_id'] ?? 0 }}
                                @else
                                    —
                                @endif
                            </td></tr>
                        <tr><th class="bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Aria gudang</th>
                            <td class="px-4 py-3">
                                @if(($result['warehouse_id'] ?? 0) > 0)
                                    #{{ $result['warehouse_id'] }} {{ $result['warehouse_name'] ?? '' }}
                                @else
                                    <span class="text-amber-800">Tidak ter-resolve — cek product_location_id vs <a href="{{ route('shopee.sync.index') }}" class="underline">Warehouse Map</a>.</span>
                                @endif
                            </td></tr>
                        @endif
                    </tbody>
                </table>
            </div>

            @if(($result['detail_loaded'] ?? false) && ! empty($result['line_summary']['lines'] ?? []))
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">Baris Shopee ({{ $result['line_summary']['line_count'] ?? 0 }})</h2>
                    <ul class="mt-2 list-inside list-disc text-sm text-gray-600 font-mono text-xs">
                        @foreach($result['line_summary']['lines'] as $line)
                            <li>{{ $line['sku'] }} × {{ $line['qty'] }}</li>
                        @endforeach
                    </ul>
                    @if(filled($result['line_match']['error'] ?? null))
                        <p class="mt-2 text-sm text-red-700">{{ $result['line_match']['error'] }}</p>
                    @elseif(($result['line_match']['item_count'] ?? 0) > 0)
                        <p class="mt-2 text-sm text-green-800">{{ $result['line_match']['item_count'] }} baris cocok di Aria.</p>
                    @endif
                </div>
            @elseif(($result['ok'] ?? false) && ! ($result['detail_loaded'] ?? false))
                <p class="text-sm text-gray-600">Status, pickup, gudang, dan baris item membutuhkan <strong>Muat detail</strong> atau centang pada form.</p>
            @endif

            @if(! empty($result['sync_matches']))
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">Matching shopee_syncs rows</h2>
                    <ul class="mt-2 list-inside list-disc text-sm text-gray-600">
                        @foreach($result['sync_matches'] as $match)
                            <li class="font-mono text-xs">
                                #{{ $match['id'] }} gudang {{ $match['warehouse_name'] }}
                                (loc {{ $match['shopee_location_id'] ?: '—' }}, wh {{ $match['shopee_warehouse_id'] }})
                                @if($match['matched_resolved']) <span class="text-green-700">← resolved</span> @endif
                                @if($match['matched_pickup']) <span class="text-blue-700">← pickup match</span> @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(($result['detail_loaded'] ?? false) && ! empty($result['api_snippet']))
                <details class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-xs">
                    <summary class="cursor-pointer font-medium text-gray-800">API snippet</summary>
                    <pre class="mt-3 max-h-96 overflow-auto whitespace-pre-wrap font-mono text-gray-700">{{ json_encode($result['api_snippet'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            @endif
        </div>
    @endif
</div>
@endsection