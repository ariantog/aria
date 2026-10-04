@if($paginator->hasPages())
<div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200/80 bg-gray-50/50 px-4 py-3 text-sm">
    <div class="text-gray-500">
        Showing <span class="font-medium">{{ $paginator->firstItem() ?? 0 }}</span>
        to <span class="font-medium">{{ $paginator->lastItem() ?? 0 }}</span>
        of <span class="font-medium">{{ $paginator->total() }}</span> {{ $label ?? 'records' }}
    </div>
    <div class="flex items-center gap-1">
        @php $window = $paginator->getUrlRange(max(1,$paginator->currentPage()-2), min($paginator->lastPage(), $paginator->currentPage()+2)); @endphp
        <a href="{{ $paginator->previousPageUrl() ?: '#' }}"
           class="ui-btn ui-btn-secondary ui-btn-sm !px-2.5 !py-1 text-xs {{ $paginator->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}">Prev</a>
        @foreach($window as $page => $url)
            <a href="{{ $url }}"
               class="ui-btn ui-btn-sm !px-2.5 !py-1 text-xs {{ $page == $paginator->currentPage() ? 'ui-btn-primary !shadow-none' : 'ui-btn-secondary' }}">{{ $page }}</a>
        @endforeach
        <a href="{{ $paginator->nextPageUrl() ?: '#' }}"
           class="ui-btn ui-btn-secondary ui-btn-sm !px-2.5 !py-1 text-xs {{ $paginator->hasMorePages() ? '' : 'pointer-events-none opacity-40' }}">Next</a>
    </div>
</div>
@endif
