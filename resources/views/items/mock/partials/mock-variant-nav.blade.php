@php
    $mockSurface = $mockSurface ?? 'parent';
    $groupId = $groupId ?? null;
    $colorwayGroupId = $colorwayGroupId ?? null;
    $itemId = $itemId ?? null;
    $active = strtolower((string) ($activeVariant ?? 'a'));

    $variants = match ($mockSurface) {
        'parent' => [
            'a' => ['label' => 'A · Tabbed hub', 'desc' => 'Tabs: colorways / style defaults / overview'],
            'b' => ['label' => 'B · Colorway-first', 'desc' => 'Like live parent page + title column'],
            'c' => ['label' => 'C · Single scroll', 'desc' => 'One page, scope chips, no tabs'],
            'd' => ['label' => 'D · Live + labels', 'desc' => 'Current layout, renamed fields only'],
        ],
        'colorway' => [
            'a' => ['label' => 'A · Split panels', 'desc' => 'Identity block + catalog + preview'],
            'b' => ['label' => 'B · Matrix first', 'desc' => 'Size prices on top, catalog below'],
            'c' => ['label' => 'C · Scope chips', 'desc' => 'One form, per-field scope selector'],
        ],
        'sku' => [
            'a' => ['label' => 'A · SKU-only (current)', 'desc' => 'Links to parent / colorway'],
            'b' => ['label' => 'B · Inline inherit', 'desc' => 'Breadcrumb + effective title readout'],
        ],
        default => [],
    };
@endphp
@if($variants !== [] && ($groupId || $colorwayGroupId || $itemId))
<nav class="mb-4 rounded-xl border border-gray-200 bg-white p-3 shadow-sm" data-testid="mock-variant-nav" aria-label="Mock layout variants">
    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Compare mock layouts</p>
    <div class="flex flex-wrap gap-2">
        @foreach($variants as $key => $meta)
            @php
                $href = match ($mockSurface) {
                    'parent' => $groupId
                        ? ($key === 'a'
                            ? route('items.catalog-mock.parent-hub', $groupId)
                            : route('items.catalog-mock.parent-hub.variant', ['group' => $groupId, 'variant' => $key]))
                        : '#',
                    'colorway' => $colorwayGroupId
                        ? ($key === 'a'
                            ? route('items.catalog-mock.colorway', $colorwayGroupId)
                            : route('items.catalog-mock.colorway.variant', ['group' => $colorwayGroupId, 'variant' => $key]))
                        : '#',
                    'sku' => $itemId
                        ? ($key === 'a'
                            ? route('items.catalog-mock.sku', $itemId)
                            : route('items.catalog-mock.sku.variant', ['item' => $itemId, 'variant' => $key]))
                        : '#',
                    default => '#',
                };
                $isActive = $active === $key;
            @endphp
            <a href="{{ $href }}"
               title="{{ $meta['desc'] }}"
               class="rounded-lg border px-3 py-2 text-sm font-medium transition-colors {{ $isActive ? 'border-indigo-600 bg-indigo-50 text-indigo-900' : 'border-gray-200 bg-gray-50 text-gray-700 hover:border-gray-300 hover:bg-white' }}"
               data-testid="mock-variant-{{ $key }}">
                {{ $meta['label'] }}
            </a>
        @endforeach
    </div>
</nav>
@endif
