@php
/** @var list<array<string, mixed>> $events */
/** @var \App\Services\Marketplace\MarketplaceWebhookStatusPresenter|null $statusPresenter */
$events = $events ?? [];
$channel = $channel ?? ($detail['channel'] ?? 'shopee');
$statusPresenter ??= app(\App\Services\Marketplace\MarketplaceWebhookStatusPresenter::class);
@endphp

<div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800/50" data-testid="omnichannel-webhook-log">
    <p class="text-[10px] font-semibold uppercase text-gray-500">Webhook log</p>
    @if($events !== [])
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
            @php
                $eventLabel = $statusPresenter->eventTypeLabel($channel, $ev['event_type'] ?? null);
            @endphp
            <tr class="border-b border-gray-50">
                <td class="py-1 pr-2 font-mono text-[10px]">{{ isset($ev['received_at']) ? \Illuminate\Support\Str::limit($ev['received_at'], 19, '') : '—' }}</td>
                <td class="py-1 pr-2">
                    <span title="code {{ $ev['event_type'] ?? '—' }}">{{ $eventLabel }}</span>
                </td>
                <td class="py-1 pr-2">{{ $statusPresenter->displayStatus($channel, $ev['order_status'] ?? null) }}</td>
                <td class="py-1 text-center">{{ ($ev['signature_ok'] ?? false) ? '✓' : '✗' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="mt-2 text-[11px] text-gray-500">Belum ada webhook event tersimpan untuk order ini.</p>
    @endif
</div>