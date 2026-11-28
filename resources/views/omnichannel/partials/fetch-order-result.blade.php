@php
    $result = is_array($fetchResult ?? null) ? $fetchResult : [];
    if ($result === [] || ! ($result['ok'] ?? false)) {
        return;
    }
    $channel = (string) ($result['channel'] ?? '');
    $orderRef = (string) ($result['order_ref'] ?? '');
    $status = (string) ($result['order_status'] ?? '—');
    $shopRef = filled($result['shop_ref'] ?? null) ? (string) $result['shop_ref'] : '—';
    $platform = filled($result['commerce_platform'] ?? null) ? strtoupper((string) $result['commerce_platform']) : null;
    $channelLabel = match (true) {
        $platform === 'TOKOPEDIA' => 'Tokopedia',
        $channel === 'shopee' => 'Shopee',
        $channel === 'tiktok' => 'TikTok Shop',
        default => ucfirst($channel ?: '—'),
    };
    $ordersListChannel = $channel === 'tiktok' && $platform === 'TOKOPEDIA' ? 'tokopedia' : $channel;
    $detailUrl = $orderRef !== '' && $channel !== ''
        ? route('omnichannel.orders.show', ['channel' => $channel, 'orderRef' => $orderRef])
        : null;
    $currentOrdersUrl = $orderRef !== ''
        ? route('omnichannel.orders.index', array_filter([
            'view' => 'orders',
            'channel' => $ordersListChannel !== '' ? $ordersListChannel : null,
            'order_ref' => $orderRef,
        ]))
        : route('omnichannel.orders.index', ['view' => 'orders']);
    $returnsUrl = ($result['return_signal'] ?? false) && $orderRef !== ''
        ? route('omnichannel.returns.index', ['invoice' => $orderRef])
        : null;
@endphp

<div class="rounded-xl border border-green-200 bg-green-50 shadow-sm dark:border-green-800 dark:bg-green-950/30" data-testid="omnichannel-fetch-order-result">
    <div class="border-b border-green-200/80 px-4 py-3 dark:border-green-800/80">
        <p class="text-sm font-semibold text-green-900 dark:text-green-100">Order berhasil ditarik dari API</p>
        <p class="mt-0.5 text-xs text-green-800/90 dark:text-green-200/90">Baris unified diperbarui — sama seperti webhook masuk.</p>
    </div>
    <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-white/60 bg-white/70 px-3 py-2 dark:border-green-900/40 dark:bg-gray-900/40">
            <p class="text-[10px] font-semibold uppercase text-gray-500">Channel</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $channelLabel }}</p>
        </div>
        <div class="rounded-lg border border-white/60 bg-white/70 px-3 py-2 dark:border-green-900/40 dark:bg-gray-900/40">
            <p class="text-[10px] font-semibold uppercase text-gray-500">Order ref</p>
            <p class="mt-1 font-mono text-sm font-medium text-gray-900 dark:text-gray-100">{{ $orderRef }}</p>
        </div>
        <div class="rounded-lg border border-white/60 bg-white/70 px-3 py-2 dark:border-green-900/40 dark:bg-gray-900/40">
            <p class="text-[10px] font-semibold uppercase text-gray-500">Status platform</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $status }}</p>
        </div>
        <div class="rounded-lg border border-white/60 bg-white/70 px-3 py-2 dark:border-green-900/40 dark:bg-gray-900/40">
            <p class="text-[10px] font-semibold uppercase text-gray-500">Shop</p>
            <p class="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $shopRef }}</p>
        </div>
    </div>
    <div class="flex flex-wrap gap-2 border-t border-green-200/80 px-4 py-3 dark:border-green-800/80">
        @if($detailUrl)
        <a href="{{ $detailUrl }}" class="inline-flex h-9 items-center rounded-lg bg-blue-700 px-4 text-sm font-medium text-white hover:bg-blue-800" data-testid="fetch-order-result-detail-link">
            Buka detail order
        </a>
        @endif
        <a href="{{ $currentOrdersUrl }}" class="inline-flex h-9 items-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" data-testid="fetch-order-result-orders-link">
            Current orders
        </a>
        @if($returnsUrl)
        <a href="{{ $returnsUrl }}" class="inline-flex h-9 items-center rounded-lg border border-amber-300 bg-amber-50 px-3 text-sm font-medium text-amber-900 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100" data-testid="fetch-order-result-returns-link">
            Marketplace returns
        </a>
        @endif
        <a href="{{ route('omnichannel.fetch-order.index', ['channel' => request()->query('channel', $channel)]) }}" class="inline-flex h-9 items-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
            Tarik order lain
        </a>
    </div>
    @if(filled($result['warning'] ?? null))
    <div class="border-t border-amber-200/80 bg-amber-50/80 px-4 py-2 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
        {{ $result['warning'] }}
    </div>
    @endif
    @if($result['return_signal'] ?? false)
    <div class="border-t border-amber-200/80 bg-amber-50/80 px-4 py-2 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100" data-testid="fetch-order-result-return-hint">
        Sinyal return terdeteksi — verifikasi penerimaan fisik sebelum posting RETURN.
    </div>
    @endif
</div>