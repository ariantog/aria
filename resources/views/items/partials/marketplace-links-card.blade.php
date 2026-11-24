@php
    $shopeePerms = \App\Models\ShopeeStock::getPermissions();
    $tiktokPerms = \App\Models\TikTokShop::getPermissions();

    $showJubelio = true;
    $showShopee = \Illuminate\Support\Facades\Gate::check($shopeePerms['view']);
    $showTikTok = \Illuminate\Support\Facades\Gate::any([
        $tiktokPerms['item_link'],
        $tiktokPerms['view'],
    ]);

    $jubelioLinked = (int) ($item->jubelio_item_id ?? 0) > 0;
    $shopeeLinked = (int) ($item->shopee_item_id ?? 0) > 0;
    $tiktokLinked = filled($item->tiktok_shop_product_id);

    $jubelioHref = route('items.jubelio', $item);
    $shopeeHref = route('items.shopee', $item);
    $tiktokHref = route('items.tiktok-shop', $item);
@endphp
@if($showJubelio || $showShopee || $showTikTok)
<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" data-testid="item-marketplace-links-card">
    <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-900">Marketplace links</h3>
        <p class="mt-0.5 text-xs text-gray-500">Channel product IDs — kelola detail di tab masing-masing.</p>
    </div>
    <div class="divide-y divide-gray-100">
        @if($showJubelio)
        <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between" data-testid="item-marketplace-jubelio-row">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-widest text-blue-700">Jubelio</p>
                <p class="mt-1 text-sm text-gray-900">
                    @if($jubelioLinked)
                        <span class="inline-flex rounded-full border border-blue-200 bg-blue-100 px-2.5 py-0.5 font-mono text-xs text-blue-800" data-testid="item-marketplace-jubelio-id">{{ $item->jubelio_item_id }}</span>
                    @else
                        <span class="text-gray-500" data-testid="item-marketplace-jubelio-unlinked">Not linked</span>
                    @endif
                </p>
            </div>
            <a href="{{ $jubelioHref }}"
               data-testid="item-marketplace-jubelio-link"
               class="inline-flex shrink-0 items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                {{ $jubelioLinked ? 'Kelola' : 'Link' }}
            </a>
        </div>
        @endif
        @if($showShopee)
        <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between" data-testid="item-marketplace-shopee-row">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-widest text-orange-700">Shopee</p>
                <p class="mt-1 text-sm text-gray-900">
                    @if($shopeeLinked)
                        <span class="inline-flex rounded-full border border-orange-200 bg-orange-100 px-2.5 py-0.5 font-mono text-xs text-orange-900" data-testid="item-marketplace-shopee-id">
                            {{ $item->shopee_item_id }}@if((int) ($item->shopee_model_id ?? 0) > 0)/{{ $item->shopee_model_id }}@endif
                        </span>
                    @else
                        <span class="text-gray-500" data-testid="item-marketplace-shopee-unlinked">Not linked</span>
                    @endif
                </p>
            </div>
            <a href="{{ $shopeeHref }}"
               data-testid="item-marketplace-shopee-link"
               class="inline-flex shrink-0 items-center justify-center rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-700">
                {{ $shopeeLinked ? 'Kelola' : 'Link' }}
            </a>
        </div>
        @endif
        @if($showTikTok)
        <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between" data-testid="item-marketplace-tiktok-row">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-widest text-violet-700">TikTok / Tokopedia</p>
                <p class="mt-1 text-sm text-gray-900">
                    @if($tiktokLinked)
                        <span class="font-mono text-xs text-violet-900 break-all" data-testid="item-marketplace-tiktok-id" title="{{ $item->tiktok_shop_product_id }}/{{ $item->tiktok_shop_sku_id }}">
                            {{ $item->tiktok_shop_product_id }}@if(filled($item->tiktok_shop_sku_id))<span class="text-gray-500"> / </span>{{ $item->tiktok_shop_sku_id }}@endif
                        </span>
                    @else
                        <span class="text-gray-500" data-testid="item-marketplace-tiktok-unlinked">Not linked</span>
                    @endif
                </p>
            </div>
            <a href="{{ $tiktokHref }}"
               data-testid="item-marketplace-tiktok-link"
               class="inline-flex shrink-0 items-center justify-center rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                {{ $tiktokLinked ? 'Kelola' : 'Link' }}
            </a>
        </div>
        @endif
    </div>
</div>
@endif