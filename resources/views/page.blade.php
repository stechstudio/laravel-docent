@extends('docent::layout')

@section('content')
    <article class="docent-prose">
        {!! $html !!}
    </article>

    @if($prev || $next)
        <nav class="docent-pagination" aria-label="Pagination">
            @if($prev)
                <a class="docent-pagination-prev" rel="prev" href="{{ $prev->url }}">
                    <span class="docent-pagination-label">Previous</span>
                    <span class="docent-pagination-title">{{ $prev->title }}</span>
                </a>
            @endif
            @if($next)
                <a class="docent-pagination-next" rel="next" href="{{ $next->url }}">
                    <span class="docent-pagination-label">Next</span>
                    <span class="docent-pagination-title">{{ $next->title }}</span>
                </a>
            @endif
        </nav>
    @endif
@endsection
