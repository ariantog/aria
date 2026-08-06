@php
$activeCode = $activeTypeTag?->code;
@endphp
<div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <label for="restock-type-select" class="shrink-0 text-sm font-medium text-gray-700">Product type</label>
        <select id="restock-type-select"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 sm:max-w-md"
                onchange="if (this.value) window.location.href = this.value">
            @foreach($typeTags as $tag)
                <option value="{{ route('restock.type.show', $tag) }}" @selected($activeCode === $tag->code)>
                    {{ $tag->name }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 sm:ml-auto flex items-center gap-3">
            <a href="{{ route('restock.settings.edit') }}" class="text-blue-600 hover:underline">Settings</a>
            <span>{{ $typeTags->count() }} types</span>
        </p>
    </div>
</div>
