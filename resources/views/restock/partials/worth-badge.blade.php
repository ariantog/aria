@php
$worth = $worth ?? [];
$pattern = $worth['pattern'] ?? 'moderate';
$badgeClass = match ($pattern) {
    \App\Services\Restock\RestockSkuConfidenceService::PATTERN_HERO => 'bg-amber-100 text-amber-950 ring-amber-200',
    \App\Services\Restock\RestockSkuConfidenceService::PATTERN_STABLE => 'bg-emerald-100 text-emerald-900 ring-emerald-200',
    \App\Services\Restock\RestockSkuConfidenceService::PATTERN_SPIKE => 'bg-sky-100 text-sky-900 ring-sky-200',
    \App\Services\Restock\RestockSkuConfidenceService::PATTERN_FATIGUE => 'bg-rose-100 text-rose-900 ring-rose-200',
    default => 'bg-gray-100 text-gray-800 ring-gray-200',
};
$confidence = $worth['confidence'] ?? 'medium';
$confidenceClass = match ($confidence) {
    \App\Services\Restock\RestockSkuConfidenceService::CONFIDENCE_HIGH => 'text-emerald-700',
    \App\Services\Restock\RestockSkuConfidenceService::CONFIDENCE_LOW => 'text-rose-700',
    default => 'text-gray-600',
};
@endphp
<div class="flex flex-col gap-0.5" data-testid="restock-worth-{{ $itemId ?? '' }}">
    <span class="inline-flex w-fit items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $badgeClass }}">
        {{ $worth['pattern_label'] ?? 'Moderate' }}
    </span>
    <span class="text-xs {{ $confidenceClass }}">{{ $worth['confidence_label'] ?? '' }}</span>
    @if(! empty($worth['detail']))
        <span class="text-xs text-gray-500">{{ $worth['detail'] }}</span>
    @endif
</div>
