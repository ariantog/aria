@php
    $multiple = $multiple ?? false;
    $selected = $selected ?? ($multiple ? [] : null);
    $errorBorder = $errorBorder ?? false;
    $onChange = $onChange ?? null;
    $selectedIds = $multiple
        ? array_map('strval', (array) $selected)
        : [(string) ($selected ?? '')];
@endphp
<div class="max-h-48 space-y-1 overflow-y-auto rounded-lg border p-2 @if($errorBorder) border-red-500 @else border-gray-300 @endif"
     data-testid="tag-picker-{{ $field }}">
    @foreach($tags as $t)
        @php $isChecked = in_array((string) $t->id, $selectedIds, true); @endphp
        <label x-show="tagOptionVisible('{{ $field }}', @js($t->name), @js($t->code))"
               class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-gray-50">
            <input type="{{ $multiple ? 'checkbox' : 'radio' }}"
                   name="{{ $inputName }}"
                   value="{{ $t->id }}"
                   data-code="{{ $t->code }}"
                   @checked($isChecked)
                   @if($onChange) @change="{{ $onChange }}($event)" @endif
                   class="{{ $multiple ? 'rounded' : 'text-blue-600' }} border-gray-300 focus:ring-blue-500">
            <span>{{ $t->name }}@if(strtoupper($t->name) !== strtoupper($t->code)) <span class="text-xs text-gray-400">({{ $t->code }})</span>@endif</span>
        </label>
    @endforeach
    <p x-show="String(tagFilters.{{ $field }} || '').trim() && !tagHasVisibleOptions('{{ $field }}')"
       x-cloak
       class="px-2 py-1 text-sm text-gray-400">No matches.</p>
</div>
