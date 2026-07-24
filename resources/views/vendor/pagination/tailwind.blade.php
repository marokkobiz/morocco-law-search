@if ($paginator->hasPages())
    <nav class="flex items-center justify-center gap-1">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="px-3 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-200 rounded-lg cursor-not-allowed">
                &lsaquo;
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:border-gray-300 transition-colors">
                &lsaquo;
            </a>
        @endif

        {{-- Page Numbers --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-3 py-2 text-sm font-medium text-gray-400">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="px-3 py-2 text-sm font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg cursor-default">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:border-gray-300 transition-colors">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:border-gray-300 transition-colors">
                &rsaquo;
            </a>
        @else
            <span class="px-3 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-200 rounded-lg cursor-not-allowed">
                &rsaquo;
            </span>
        @endif
    </nav>
@endif
