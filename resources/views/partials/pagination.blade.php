@if($paginator->hasPages())
<div class="flex items-center justify-between border-t border-gray-200 bg-gray-50/40 px-4 py-3 text-sm">
    <div class="text-gray-500">
        Showing <span class="font-medium">{{ $paginator->firstItem() ?? 0 }}</span>
        to <span class="font-medium">{{ $paginator->lastItem() ?? 0 }}</span>
        of <span class="font-medium">{{ $paginator->total() }}</span> {{ $label ?? 'records' }}
    </div>
    <div class="flex items-center gap-1">
        @php $window = $paginator->getUrlRange(max(1,$paginator->currentPage()-2), min($paginator->lastPage(), $paginator->currentPage()+2)); @endphp
        <a href="{{ $paginator->previousPageUrl() ?: '#' }}"
           class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs {{ $paginator->onFirstPage() ? 'pointer-events-none opacity-40' : 'hover:bg-gray-50' }}">Prev</a>
        @foreach($window as $page => $url)
            <a href="{{ $url }}"
               class="rounded-md border px-2.5 py-1 text-xs {{ $page == $paginator->currentPage() ? 'border-blue-700 bg-blue-700 text-white' : 'border-gray-200 bg-white hover:bg-gray-50' }}">{{ $page }}</a>
        @endforeach
        <a href="{{ $paginator->nextPageUrl() ?: '#' }}"
           class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs {{ $paginator->hasMorePages() ? 'hover:bg-gray-50' : 'pointer-events-none opacity-40' }}">Next</a>
    </div>
</div>
@endif
