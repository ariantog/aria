@php
/** @var array<string, mixed> $detail */
$shadow = $detail['shadow'] ?? null;
$links = $detail['links'] ?? [];
$events = $detail['webhook_events'] ?? [];
$jubelio = $detail['jubelio'] ?? null;
$isTiktok = ($detail['channel'] ?? '') === 'tiktok';
$tiktokLines = $detail['line_summary'] ?? [];
$channelOrder = $detail['channel_order'] ?? null;
$apiDetailLoaded = (bool) ($detail['api_detail_loaded'] ?? false);
$apiError = $detail['api_error'] ?? null;
$apiDetail = $detail['api_detail'] ?? null;
@endphp

<div class="space-y-3 p-3 text-xs text-gray-800" data-testid="omnichannel-order-detail">
    @if(!empty($detail['commerce_platform']))
    <p class="text-gray-600">
        Commerce platform:
        <strong class="uppercase" data-testid="omnichannel-order-commerce-platform">{{ $detail['commerce_platform'] }}</strong>
    </p>
    @endif

    @php
        $awbPrint = $detail['awb_print'] ?? null;
        $awbPrintHistory = $detail['awb_print_history'] ?? [];
    @endphp
    @if(!empty($awbPrint['user_id']))
    <p class="text-gray-600" data-testid="omnichannel-order-awb-print-detail">
        AWB terakhir dicetak oleh
        <strong>{{ $awbPrint['username'] ?? 'user #'.$awbPrint['user_id'] }}</strong>
        @if(!empty($awbPrint['printed_at']))
        <span class="text-gray-500">· {{ \Illuminate\Support\Carbon::parse($awbPrint['printed_at'])->format('Y-m-d H:i:s') }}</span>
        @endif
    </p>
    @endif
    @if(count($awbPrintHistory) > 0)
    <div class="text-gray-600" data-testid="omnichannel-order-awb-print-history">
        <p class="text-[10px] font-semibold uppercase text-gray-500">Riwayat cetak AWB</p>
        <ul class="mt-1 list-inside list-disc text-[11px] text-gray-700">
            @foreach($awbPrintHistory as $hist)
            <li>
                {{ $hist['username'] ?? 'user #'.($hist['user_id'] ?? 0) }}
                @if(!empty($hist['printed_at']))
                · {{ \Illuminate\Support\Carbon::parse($hist['printed_at'])->format('Y-m-d H:i:s') }}
                @endif
                @if(!empty($hist['is_reprint']))
                <span class="text-amber-700">(cetak ulang)</span>
                @endif
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @php $warehouse = $detail['warehouse'] ?? []; @endphp
    @if(!empty($warehouse['aria_name']) || !empty($warehouse['platform_label']) || !empty($warehouse['unmapped']))
    <div class="rounded-lg border border-gray-200 bg-white p-3" data-testid="omnichannel-order-warehouse">
        <p class="text-[10px] font-semibold uppercase text-gray-500">Warehouse</p>
        <dl class="mt-2 grid grid-cols-1 gap-2 text-[11px] sm:grid-cols-2">
            <div>
                <dt class="text-gray-500">Aria gudang</dt>
                <dd class="font-medium text-gray-900">
                    @if(!empty($warehouse['aria_name']))
                    {{ $warehouse['aria_name'] }}
                    @elseif(!empty($warehouse['unmapped']))
                    <span class="text-gray-500">Not mapped</span>
                    @else
                    —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Platform WH</dt>
                <dd class="font-mono text-gray-800">{{ $warehouse['platform_label'] ?? '—' }}</dd>
            </div>
        </dl>
    </div>
    @endif
    @php $sellPost = $detail['sell_post'] ?? null; @endphp
    @if(is_array($sellPost))
    <div class="rounded-lg border border-gray-200 bg-white p-3" data-testid="omnichannel-order-sell-post-detail">
        <p class="text-[10px] font-semibold uppercase text-gray-500">Native SELL / invoice</p>
        <dl class="mt-2 grid grid-cols-1 gap-2 text-[11px] sm:grid-cols-2">
            <div>
                <dt class="text-gray-500">Invoice</dt>
                <dd class="font-mono font-medium text-gray-900">{{ $sellPost['invoice'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Submit source</dt>
                <dd class="font-medium text-gray-900" data-testid="omnichannel-order-sell-submit-source">
                    @if(! empty($sellPost['submit_type_label']))
                    {{ $sellPost['submit_type_label'] }}
                    @else
                    <span class="text-gray-400">Belum diposting</span>
                    @endif
                </dd>
            </div>
            @if(! empty($sellPost['transaction_url']))
            <div class="sm:col-span-2">
                <dt class="text-gray-500">Transaksi Aria</dt>
                <dd>
                    <a href="{{ $sellPost['transaction_url'] }}" class="font-medium text-blue-700 hover:underline">
                        SELL #{{ $sellPost['posted_transaction_id'] ?? '—' }}
                    </a>
                    @if(! empty($sellPost['posted_at']))
                    <span class="text-gray-500">· posted {{ \Illuminate\Support\Carbon::parse($sellPost['posted_at'])->format('Y-m-d H:i') }}</span>
                    @endif
                </dd>
            </div>
            @endif
        </dl>
    </div>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        @if(!empty($links['shadow_compare']))
        <a href="{{ $links['shadow_compare'] }}" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50">Shadow compare Shopee</a>
        @endif
        @if(!empty($links['jubelio']))
        <a href="{{ $links['jubelio'] }}" class="rounded-md border border-blue-200 bg-blue-50 px-2 py-1 text-[11px] font-medium text-blue-800 hover:bg-blue-100">Jubelio #{{ $jubelio['id'] ?? '' }}</a>
        @endif
        @if(empty($links['shadow_compare']) && empty($links['jubelio']))
        <span class="text-gray-500">Tidak ada tautan detail tambahan.</span>
        @endif
    </div>

    @if($isTiktok)
    <div class="rounded-lg border border-gray-200 bg-white p-3" data-testid="omnichannel-tiktok-api-detail">
        <p class="text-[10px] font-semibold uppercase text-gray-500">TikTok Get Order Detail</p>
        @if($apiError)
        <p class="mt-1 text-[11px] text-amber-800" data-testid="omnichannel-tiktok-api-error">{{ $apiError }}</p>
        @elseif($apiDetailLoaded)
        <p class="mt-1 text-[11px] text-gray-600">Detail API dimuat (read-only).</p>
        @else
        <p class="mt-1 text-[11px] text-gray-500">Detail API belum dimuat.</p>
        @endif

        @if(is_array($channelOrder) && $channelOrder !== [])
        <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-[11px] sm:grid-cols-3">
            @if(!empty($channelOrder['status']))
            <dt class="text-gray-500">Status</dt>
            <dd class="font-mono text-gray-800">{{ $channelOrder['status'] }}</dd>
            @endif
            @if(!empty($channelOrder['commerce_platform']))
            <dt class="text-gray-500">Platform</dt>
            <dd class="font-mono uppercase text-gray-800">{{ $channelOrder['commerce_platform'] }}</dd>
            @endif
            @if(!empty($channelOrder['payment_method']))
            <dt class="text-gray-500">Bayar</dt>
            <dd class="text-gray-800">{{ $channelOrder['payment_method'] }}</dd>
            @endif
            @if(isset($channelOrder['total_amount']))
            <dt class="text-gray-500">Total</dt>
            <dd class="font-mono text-gray-800">
                @if(!empty($channelOrder['currency']))
                {{ $channelOrder['currency'] }}
                @endif
                {{ $channelOrder['total_amount'] }}
            </dd>
            @endif
        </dl>
        @endif

        @if(!empty($tiktokLines) && is_array($tiktokLines))
        <p class="mt-3 text-[10px] font-semibold uppercase text-gray-500">Baris (SKU × qty)</p>
        <table class="mt-1 w-full max-w-md text-left" data-testid="omnichannel-tiktok-line-table">
            <thead>
                <tr class="border-b border-gray-100 text-[10px] uppercase text-gray-500">
                    <th class="py-1 pr-2">SKU</th>
                    <th class="py-1 text-right">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($tiktokLines, 0, 50) as $line)
                <tr class="border-b border-gray-50">
                    <td class="py-1 pr-2 font-mono text-[11px]">{{ $line['sku'] ?? '—' }}</td>
                    <td class="py-1 text-right font-mono text-[11px]">{{ $line['qty'] ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @elseif($apiDetailLoaded)
        <p class="mt-2 text-[11px] text-gray-500">Tidak ada baris SKU pada respons API (webhook mungkin tanpa item_list).</p>
        @endif

        @if($apiDetailLoaded && is_array($apiDetail))
        <details class="mt-3">
            <summary class="cursor-pointer text-[10px] font-semibold uppercase text-gray-500 hover:text-gray-700">JSON API (ringkas)</summary>
            <pre class="mt-2 max-h-48 overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-2 font-mono text-[10px] leading-relaxed text-gray-800 whitespace-pre-wrap break-all">{{ json_encode($apiDetail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
        @endif
    </div>
    @endif

    @if($shadow)
    <div class="rounded-lg border border-gray-200 bg-white p-3">
        <p class="text-[10px] font-semibold uppercase text-gray-500">Shadow (stored)</p>
        <p class="mt-1">Compare: <strong>{{ $shadow['compare_status'] ?? '—' }}</strong>
            · Baris: <strong>{{ $shadow['line_compare_status'] ?? '—' }}</strong>
            @if(!($shadow['detail_loaded'] ?? false))
            <span class="text-gray-400">— detail API belum dimuat (buka shadow compare untuk enrich).</span>
            @endif
        </p>
        @if(!empty($shadow['line_summary']) && is_array($shadow['line_summary']))
        <p class="mt-2 text-[10px] font-semibold uppercase text-gray-500">Line summary (Shopee)</p>
        <ul class="mt-1 list-inside list-disc text-gray-600">
            @foreach(array_slice($shadow['line_summary'], 0, 8) as $line)
            <li>{{ is_array($line) ? json_encode($line, JSON_UNESCAPED_UNICODE) : $line }}</li>
            @endforeach
        </ul>
        @endif
    </div>
    @endif

    @if(!empty($detail['ingress']))
    <div class="rounded-lg border border-gray-200 bg-white p-3">
        <p class="mb-2 text-[10px] font-semibold uppercase text-gray-500">Ingress timeline</p>
        @include('omnichannel.partials.ingress-timeline', ['ingress' => $detail['ingress'], 'compact' => true])
    </div>
    @endif

    @include('omnichannel.partials.webhook-log', [
        'events' => $events,
        'channel' => $detail['channel'] ?? 'shopee',
        'statusPresenter' => $statusPresenter ?? null,
    ])
</div>