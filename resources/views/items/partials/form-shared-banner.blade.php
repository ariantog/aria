@php
    $sharedTitle = $sharedTitle ?? 'Shared across this colorway';
    $sharedHint = $sharedHint ?? 'These attributes belong to the item group. Every size in this colorway uses the same values.';
    $sharedTestId = $sharedTestId ?? 'item-form-shared-banner';
    $sharedTone = $sharedTone ?? 'shared';
    $toneClasses = $sharedTone === 'sku'
        ? 'border-gray-200 bg-gray-50 text-gray-800'
        : 'border-indigo-200 bg-indigo-50 text-indigo-900';
    $titleClasses = $sharedTone === 'sku' ? 'text-gray-700' : 'text-indigo-800';
@endphp
<div class="rounded-lg border px-3 py-2 {{ $toneClasses }}" data-testid="{{ $sharedTestId }}">
    <p class="text-xs font-semibold uppercase tracking-wide {{ $titleClasses }}">{{ $sharedTitle }}</p>
    <p class="mt-0.5 text-xs opacity-80">{{ $sharedHint }}</p>
</div>
