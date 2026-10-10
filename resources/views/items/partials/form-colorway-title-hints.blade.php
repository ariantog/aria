@php
    $titleForm = $titleForm ?? [];
    $effective = trim((string) ($titleForm['effective_title'] ?? ''));
    $parent = trim((string) ($titleForm['parent_title'] ?? ''));
    $inherits = (bool) ($titleForm['inherits_parent'] ?? false);
    $usesPlaceholder = (bool) ($titleForm['uses_placeholder'] ?? false);
@endphp
@if($effective !== '' || $parent !== '')
<div class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700" data-testid="colorway-title-hints">
    @if($effective !== '')
    <p>
        <span class="font-semibold text-gray-900">Effective bare title (what lists and SKU names use):</span>
        <span class="font-mono">{{ $effective }}</span>
    </p>
    @endif
    @if($usesPlaceholder && $parent !== '')
    <p class="mt-1 text-gray-600">
        This colorway has no custom title stored on <span class="font-mono">item_group.name</span>.
        It inherits the parent group name above until you enter a colorway-specific title here.
    </p>
    @elseif($usesPlaceholder && $parent === '')
    <p class="mt-1 text-gray-600">
        No custom colorway title is stored yet. Leave blank to keep using the pcode as the bare title, or set a name for this color only.
    </p>
    @elseif($inherits)
    <p class="mt-1 text-gray-600">Currently inheriting the parent group title. Enter a value here to override for this colorway only.</p>
    @endif
    @if($parent !== '' && ! $inherits && $usesPlaceholder)
    <p class="mt-1 text-gray-600">Parent group default: <span class="font-mono">{{ $parent }}</span></p>
    @endif
</div>
@endif
