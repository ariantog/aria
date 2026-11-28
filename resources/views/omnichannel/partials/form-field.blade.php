@php
    require resource_path('views/partials/form-control-class-vars.php');
    $name = $name ?? '';
    $id = $id ?? $name;
    $label = $label ?? '';
    $type = $type ?? 'text';
    $value = $value ?? '';
    $required = ! empty($required);
    $placeholder = $placeholder ?? '';
    $mono = ! empty($mono);
    $hint = $hint ?? null;
    $testid = $testid ?? null;
    $options = $options ?? [];
    $selected = $selected ?? null;
    $attributes = $attributes ?? '';
    $inputClass = trim(($mono ? 'font-mono ' : '').$formControlClassSm.' '.($extraClass ?? ''));
@endphp

<div class="flex min-w-[8rem] flex-col gap-1 {{ $wrapperClass ?? '' }}"
     @if(filled($testid)) data-testid="{{ $testid }}" @endif>
    <label for="{{ $id }}" class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $label }}</label>
    @if($type === 'select')
        <select name="{{ $name }}" id="{{ $id }}" class="{{ $inputClass }}" @if($required) required @endif {!! $attributes !!}>
            @foreach($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $selected === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        </select>
    @else
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" placeholder="{{ $placeholder }}"
               class="{{ $inputClass }}" @if($required) required @endif {!! $attributes !!}>
    @endif
    @if(filled($hint))
    <p class="text-[10px] text-amber-800 dark:text-amber-300">{{ $hint }}</p>
    @endif
    @error($name)
    <p class="text-[10px] text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>