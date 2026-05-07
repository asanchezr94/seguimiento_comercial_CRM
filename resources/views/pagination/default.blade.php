@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginacion" style="margin-top:12px; display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
        @if ($paginator->onFirstPage())
            <span style="padding:6px 10px; border:1px solid #ddd; color:#888;">Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" style="padding:6px 10px; border:1px solid #ddd; text-decoration:none;">Anterior</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span style="padding:6px 10px; border:1px solid #ddd; color:#888;">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span style="padding:6px 10px; border:1px solid #333; background:#333; color:#fff;">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" style="padding:6px 10px; border:1px solid #ddd; text-decoration:none;">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" style="padding:6px 10px; border:1px solid #ddd; text-decoration:none;">Siguiente</a>
        @else
            <span style="padding:6px 10px; border:1px solid #ddd; color:#888;">Siguiente</span>
        @endif
    </nav>
@endif
