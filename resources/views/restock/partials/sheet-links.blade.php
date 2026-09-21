@php
/** @var list<array{id: int, name: string, url: string}> $links */
$links = $links ?? [];
@endphp
@if($links !== [])
    <div class="mt-0.5 flex flex-col gap-0.5 text-xs font-normal">
        @foreach($links as $sheetLink)
            <a href="{{ $sheetLink['url'] }}"
               class="text-blue-600 hover:underline"
               data-testid="restock-sheet-link-{{ $sheetLink['id'] }}-{{ $itemId ?? '' }}">
                {{ $sheetLink['name'] }}
            </a>
        @endforeach
    </div>
@endif
