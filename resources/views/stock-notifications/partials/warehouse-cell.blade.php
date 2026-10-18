@props(['warehouse', 'tdClass' => 'px-4 py-3 text-gray-700', 'copyCol' => null])

<td class="{{ $tdClass }}" @if($copyCol) data-copy-col="{{ $copyCol }}" @endif>
    @if($warehouse)
    <a href="{{ $warehouse->transactionsUrl() }}"
       class="text-blue-600 hover:underline">
        {{ $warehouse->name }}
    </a>
    @endif
</td>
