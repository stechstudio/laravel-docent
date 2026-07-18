@extends('docent::widget.layout')

@section('content')
    <article class="px-5 py-6 sm:px-6">
        <a href="{{ $docent->widgetUrl() }}" class="mb-5 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-[var(--docent-accent)] dark:text-slate-400">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            {{ __('docent::ui.widget.all_help') }}
        </a>

        <h1 class="text-[1.75rem] font-bold leading-tight tracking-tight text-slate-900 dark:text-white">{{ $title }}</h1>

        @if($description)
            <p class="mt-2 text-[15px] leading-6 text-slate-500 dark:text-slate-400">{{ $description }}</p>
        @endif

        <div class="docent-prose docent-widget-prose mt-7">
            {!! $html !!}
        </div>
    </article>
@endsection
