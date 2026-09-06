@props(['warehouse', 'tdClass' => 'px-4 py-3 text-gray-700'])

<td class="{{ $tdClass }}">
    @if($warehouse)
    <a href="{{ $warehouse->transactionsUrl() }}"
       class="text-blue-600 hover:underline">
        {{ $warehouse->name }}
    </a>
    @endif
</td>
