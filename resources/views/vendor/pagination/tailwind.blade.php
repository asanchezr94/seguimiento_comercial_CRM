@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginacion">
        <ul class="pagination">
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                @if ($paginator->onFirstPage())
                    <span aria-hidden="true">&lt;</span>
                @else
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Anterior">&lt;</a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled"><span>{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span>{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                @if ($paginator->hasMorePages())
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Siguiente">&gt;</a>
                @else
                    <span aria-hidden="true">&gt;</span>
                @endif
            </li>
        </ul>
    </nav>
@endif

