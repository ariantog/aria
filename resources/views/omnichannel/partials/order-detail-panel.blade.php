@php
/** @var array<string, mixed> $detail */
$shadow = $detail['shadow'] ?? null;
$links = $detail['links'] ?? [];
$events = $detail['webhook_events'] ?? [];
$jubelio = $detail['jubelio'] ?? null;
@endphp

<div class="space-y-3 p-3 text-xs text-gray-800" data-testid="omnichannel-order-detail">
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

    @if($events !== [])
    <div class="rounded-lg border border-gray-200 bg-white p-3">
        <p class="text-[10px] font-semibold uppercase text-gray-500">Webhook events (latest)</p>
        <table class="mt-2 w-full text-left">
            <thead>
                <tr class="border-b border-gray-100 text-[10px] uppercase text-gray-500">
                    <th class="py-1 pr-2">received</th>
                    <th class="py-1 pr-2">event</th>
                    <th class="py-1 pr-2">status</th>
                    <th class="py-1 text-center">sign</th>
                </tr>
            </thead>
            <tbody>
                @foreach($events as $ev)
                <tr class="border-b border-gray-50">
                    <td class="py-1 pr-2 font-mono text-[10px]">{{ isset($ev['received_at']) ? \Illuminate\Support\Str::limit($ev['received_at'], 19, '') : '—' }}</td>
                    <td class="py-1 pr-2">{{ $ev['event_type'] ?? '—' }}</td>
                    <td class="py-1 pr-2">{{ $ev['order_status'] ?? '—' }}</td>
                    <td class="py-1 text-center">{{ ($ev['signature_ok'] ?? false) ? '✓' : '✗' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <p class="text-gray-500">Belum ada webhook event tersimpan untuk order ini.</p>
    @endif
</div>