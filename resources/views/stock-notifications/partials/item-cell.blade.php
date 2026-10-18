@props(['notification', 'tdClass' => 'px-4 py-3', 'copyCol' => null])

<td class="{{ $tdClass }}" @if($copyCol) data-copy-col="{{ $copyCol }}" @endif>
    @if($notification->item)
    <a href="{{ $notification->item->showUrl() }}" class="block hover:text-blue-700">
        <div class="font-medium text-gray-900 hover:underline">{{ $notification->item->code }}</div>
        <div class="text-xs text-gray-500">{{ $notification->item->name }}</div>
    </a>
    @endif
</td>
