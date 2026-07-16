@extends('docent::layout')

@section('content')
    <div class="mx-auto max-w-[46rem]">
        <article>
            @if($breadcrumb)
                <p class="mb-3 text-[13px] font-semibold uppercase tracking-wide text-[var(--docent-accent)]">{{ $breadcrumb }}</p>
            @endif

            <h1 class="text-[2.25rem] font-bold leading-tight tracking-tight text-slate-900 dark:text-white">{{ $title }}</h1>

            @if($description)
                <p class="mt-3 text-lg text-slate-500 dark:text-slate-400">{{ $description }}</p>
            @endif

            <div class="docent-prose mt-8">
                {!! $html !!}
            </div>
        </article>

        @if($prev || $next)
            <nav class="docent-pagination mt-14 grid gap-4 border-t border-slate-200 pt-8 sm:grid-cols-2 dark:border-slate-800" aria-label="Pagination">
                @if($prev)
                    <a rel="prev" href="{{ $prev->url }}" class="group flex flex-col rounded-xl border border-slate-200 p-4 transition hover:border-[var(--docent-accent)] dark:border-slate-800">
                        <span class="flex items-center gap-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Previous
                        </span>
                        <span class="mt-1 font-semibold text-slate-900 group-hover:text-[var(--docent-accent)] dark:text-white">{{ $prev->title }}</span>
                    </a>
                @else
                    <span></span>
                @endif
                @if($next)
                    <a rel="next" href="{{ $next->url }}" class="group flex flex-col items-end rounded-xl border border-slate-200 p-4 text-right transition hover:border-[var(--docent-accent)] dark:border-slate-800">
                        <span class="flex items-center gap-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                            Next
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </span>
                        <span class="mt-1 font-semibold text-slate-900 group-hover:text-[var(--docent-accent)] dark:text-white">{{ $next->title }}</span>
                    </a>
                @endif
            </nav>
        @endif
    </div>
@endsection

@section('rail')
    @if(!empty($toc))
        <aside class="docent-rail docent-toc docent-scroll sticky top-16 hidden h-[calc(100vh-4rem)] w-56 shrink-0 overflow-y-auto py-10 xl:block" aria-label="On this page">
            <p class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">On this page</p>
            <ul class="space-y-1 text-sm">
                @foreach($toc as $entry)
                    @include('docent::partials.toc-entry', ['entry' => $entry])
                @endforeach
            </ul>
        </aside>
    @endif
@endsection
