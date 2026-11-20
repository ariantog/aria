@php
/** @var array<string, mixed> $ingress */
$shopee = $ingress['shopee_formatted'] ?? null;
$jubelio = $ingress['jubelio_formatted'] ?? null;
$delta = $ingress['delta_label'] ?? null;
$jubelioEarlier = (bool) ($ingress['jubelio_earlier'] ?? false);
$shopeeSource = $ingress['shopee_source'] ?? null;
$compact = (bool) ($compact ?? false);
@endphp

<div class="{{ $compact ? 'text-xs' : 'text-sm' }} text-gray-700" @if(!empty($dataTestid)) data-testid="{{ $dataTestid }}" @endif>
    <dl class="{{ $compact ? 'space-y-1' : 'grid gap-2 sm:grid-cols-2' }}">
        <div>
            <dt class="text-[10px] font-semibold uppercase text-gray-500">Shopee push</dt>
            <dd class="mt-0.5 font-mono tabular-nums text-gray-800">{{ $shopee ?? '—' }}</dd>
            @if($shopee && $shopeeSource && $shopeeSource !== 'inbox')
            <dd class="text-[10px] text-gray-400">fallback {{ $shopeeSource }}</dd>
            @endif
        </div>
        <div>
            <dt class="text-[10px] font-semibold uppercase text-gray-500">Jubelio inbox</dt>
            <dd class="mt-0.5 font-mono tabular-nums text-gray-800">
                {{ $jubelio ?? '—' }}
                @if($delta && $jubelioEarlier)
                <span class="ml-1 font-sans text-gray-400" title="Jubelio muncul lebih dulu di Aria">{{ $delta }}</span>
                @elseif($delta && !$jubelioEarlier && $delta !== '0s')
                <span class="ml-1 font-sans text-gray-400" title="Selisih vs Shopee push">{{ $delta }}</span>
                @endif
            </dd>
        </div>
    </dl>
</div>