@props(['warehouse', 'tdClass' => 'px-4 py-3 text-gray-700'])

<td class="{{ $tdClass }}">
    @if($warehouse)
    <a href="{{ route('addrbook.type.show', ['type' => $warehouse->type_slug, 'addrbook' => $warehouse->id]) }}"
       class="text-blue-600 hover:underline">
        {{ $warehouse->name }}
    </a>
    @endif
</td>
