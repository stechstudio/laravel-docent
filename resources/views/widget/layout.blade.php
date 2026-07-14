<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="docent-scroll" data-docent-widget>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? null) ? $title.' — '.$siteName : $siteName }}</title>
    <script>(function(){try{var t=localStorage.getItem('docentTheme');var d=t?t==='dark':window.matchMedia('(prefers-color-scheme: dark)').matches;document.documentElement.classList.toggle('dark',d);}catch(e){}})();</script>
    <link rel="stylesheet" href="{{ $docent->asset('docent.css') }}">
    <script defer src="{{ $docent->asset('docent.js') }}"></script>
    <style>{!! $docent->themeStyles() !!}</style>
</head>
<body data-widget-base="{{ $docent->widgetUrl() }}" data-widget-suggestions-url="{{ route('docent.widget.suggestions') }}" data-widget-slug="{{ $currentSlug }}" class="docent-widget-frame min-h-screen bg-[var(--docent-bg)] text-[var(--docent-fg)] antialiased">
    <div x-data="docentWidgetSearch('{{ route('docent.search', ['mode' => 'widget']) }}', @js(config('docent.ai.enabled', false) ? route('docent.ask') : null), @js(config('docent.ai.enabled', false) ? route('docent.ask.feedback') : null))"
         @keydown.escape.window="askMode && backToResults()"
         @docent:widget-search.window="setQuery($event.detail.query)"
         class="flex min-h-screen flex-col">
        <header class="docent-widget-header sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 px-3 py-3 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/95">
            <div class="flex items-center gap-2">
                @if($searchEnabled)
                    <div class="relative min-w-0 flex-1">
                        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input data-docent-widget-search x-ref="input" x-model="query" @input="onInput()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="enter()" :disabled="askMode"
                               type="search" autocomplete="off" spellcheck="false" placeholder="Search help…" aria-label="Search documentation"
                               class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-[var(--docent-accent)] focus:bg-white focus:outline-none dark:border-slate-800 dark:bg-slate-900 dark:text-white dark:focus:bg-slate-950">

                        <div x-show="query.trim() !== ''" x-cloak class="docent-widget-results docent-scroll absolute inset-x-0 top-[calc(100%+0.5rem)] max-h-[min(23rem,60vh)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                            <div x-show="!askMode">
                            <div x-show="loading" class="px-3 py-7 text-center text-sm text-slate-400">Searching…</div>
                            <template x-for="(result, index) in results" :key="result.slug + '-' + index">
                                <a :href="href(result)" @click.prevent="go(result)" @mouseenter="selected = index" :data-selected="selected === index"
                                   class="block rounded-lg px-3 py-2.5 transition" :class="selected === index ? 'bg-[color-mix(in_srgb,var(--docent-accent)_12%,transparent)]' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'">
                                    <span class="block text-sm font-semibold text-slate-900 dark:text-white" x-text="result.heading || result.title"></span>
                                    <span x-show="result.group" class="mt-0.5 block text-[11px] font-semibold uppercase tracking-wide text-[var(--docent-accent)]" x-text="result.group"></span>
                                </a>
                            </template>
                            <button x-show="canAsk()" type="button" @click="ask()" @mouseenter="selected = results.length" :data-selected="selected === results.length"
                                    class="mt-1 block w-full border-t border-slate-100 px-3 py-2.5 text-left transition dark:border-slate-800"
                                    :class="selected === results.length ? 'bg-[color-mix(in_srgb,var(--docent-accent)_10%,transparent)]' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'">
                                <span class="block text-sm font-semibold text-slate-900 dark:text-white">Ask the docs</span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400" x-text="query"></span>
                            </button>
                            <div x-show="searched && !loading && results.length === 0" class="px-3 py-7 text-center text-sm text-slate-400">No matching help pages.</div>
                            </div>

                            @include('docent::partials.ask-answer', ['compact' => true])
                        </div>
                    </div>
                @endif

                <a href="{{ $fullDocsUrl }}" target="_top" class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-xl px-2.5 text-xs font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" title="Open full docs">
                    <span class="hidden min-[360px]:inline">Full docs</span>
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                </a>

                <button type="button" data-docent-widget-close aria-label="Close help" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                    <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </header>

        <main id="docent-content" class="min-h-0 flex-1">
            @yield('content')
        </main>
    </div>
</body>
</html>
