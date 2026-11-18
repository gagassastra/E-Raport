@if ($paginator->hasPages())
<nav class="pagination" aria-label="Navigasi halaman">
    <div class="muted small">Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} data</div>
    <div class="pages">
        @if ($paginator->onFirstPage())<span class="p disabled">‹</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a>@endif
        @foreach ($elements as $element)
            @if (is_string($element))<span class="p disabled">{{ $element }}</span>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())<span class="p current">{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">›</a>@else<span class="p disabled">›</span>@endif
    </div>
</nav>
@endif
