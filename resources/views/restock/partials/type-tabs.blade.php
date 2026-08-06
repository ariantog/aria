@php
$activeCode = $activeTypeTag?->code;
@endphp
<div class="flex flex-wrap gap-1 border-b border-gray-200 pb-2">
    @foreach($typeTags as $tag)
        <a href="{{ route('restock.type.show', $tag) }}"
           class="rounded-md px-3 py-1.5 text-sm font-medium {{ $activeCode === $tag->code ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            {{ $tag->name }}
            @if(isset($tag->sheet_count) && $tag->sheet_count > 0)
                <span class="ml-1 text-xs opacity-80">({{ $tag->sheet_count }})</span>
            @endif
        </a>
    @endforeach
</div>
