@if($identityConvert)
@php
    $parse = $identityConvert['parse'] ?? null;
    $convertRoute = $isAsset ? 'assetlancar.convert-identity' : 'items.convert-identity';
@endphp
<div class="mb-6 overflow-hidden rounded-2xl border border-amber-200 bg-amber-50 shadow-sm">
    <div class="border-b border-amber-200 px-6 py-4">
        <h3 class="text-sm font-semibold uppercase tracking-widest text-amber-900">Legacy SKU Conversion</h3>
        <p class="mt-1 text-sm text-amber-800">
            Use this to finish SKU conversion, product group linking, and tags when the item is not fully set up yet.
        </p>
    </div>
    <div class="space-y-4 px-6 py-4">
        @if(! ($identityConvert['convertible'] ?? false))
            <div class="rounded-lg border border-amber-300 bg-white px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Cannot convert from this page</p>
                <p class="mt-1">{{ $identityConvert['message'] ?? 'SKU is not eligible for conversion.' }}</p>
                @if($parse && ! $parse->success && $parse->failureCode)
                    <p class="mt-2 font-mono text-xs text-amber-700">{{ $parse->failureCode }}</p>
                @endif
            </div>
        @else
            <div class="rounded-lg border border-amber-300 bg-white px-4 py-3 text-sm text-amber-900">
                <p>
                    Current SKU: <span class="font-mono font-semibold">{{ $item->code }}</span>
                </p>
                @if($parse?->canonicalCode && $parse->canonicalCode !== $item->code)
                    <p class="mt-2">
                        Will become: <span class="font-mono font-semibold text-green-700">{{ $parse->canonicalCode }}</span>
                    </p>
                @endif
                @if($parse?->groupName)
                    <p class="mt-1 text-amber-800">Product group: {{ $parse->groupName }}</p>
                @endif
                @if($identityConvert['message'])
                    <p class="mt-2 text-amber-800">{{ $identityConvert['message'] }}</p>
                @endif
                <p class="mt-2 text-xs text-amber-700">
                    The old SKU is saved to <span class="font-mono">legacy_code</span> when it changes, for Jubelio order matching.
                </p>
            </div>
            <form method="POST"
                  action="{{ route($convertRoute, $item) }}"
                  onsubmit="return confirm('Convert this item to the new SKU format? The current code will be preserved as legacy_code if it changes.');">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                    Convert to new SKU
                </button>
            </form>
        @endif
    </div>
</div>
@endif
