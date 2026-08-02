@php
    $base = ($isAsset ?? false) ? '/assetlancar' : '/items';
    $active = $active ?? 'Detail';
    $tabs = [
        ['Detail', $base.'/'.$item->id],
        ['Transaction', $base.'/'.$item->id.'/transactions'],
        ['Stats', $base.'/'.$item->id.'/stats'],
        ['Jubelio', route('items.jubelio', $item->id)],
    ];
@endphp
<div class="mb-6 flex overflow-x-auto border-b border-gray-200">
    @foreach($tabs as [$label, $href])
    <a href="{{ $href }}"
       class="border-b-2 px-6 py-3 text-sm font-medium transition-all {{ $active === $label ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-900' }}">
        {{ $label }}
    </a>
    @endforeach
</div>
