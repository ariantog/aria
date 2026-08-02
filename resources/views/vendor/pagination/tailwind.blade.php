@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-between gap-3">
        {{-- Result count --}}
        <p class="hidden text-xs text-gray-500 sm:block">
            Showing
            <span class="font-medium">{{ $paginator->firstItem() }}</span>
            to
            <span class="font-medium">{{ $paginator->lastItem() }}</span>
            of
            <span class="font-medium">{{ $paginator->total() }}</span>
            results
        </p>

        <div class="flex flex-1 items-center justify-end gap-1">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="cursor-not-allowed rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-300">
                    ‹ Prev
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                    ‹ Prev
                </a>
            @endif

            {{-- Page numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-1.5 text-xs text-gray-400">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                  class="rounded-md border border-blue-700 bg-blue-700 px-2.5 py-1.5 text-xs font-semibold text-white">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}"
                               class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                    Next ›
                </a>
            @else
                <span class="cursor-not-allowed rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-300">
                    Next ›
                </span>
            @endif
        </div>
    </nav>
@endif
