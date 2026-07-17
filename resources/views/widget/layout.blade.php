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
@php($aiEnabled = (bool) $docent->config('ai.enabled', false))
<body data-widget-base="{{ $docent->widgetUrl() }}" data-widget-suggestions-url="{{ $docent->route('widget.suggestions') }}" data-widget-slug="{{ $currentSlug }}" class="docent-widget-frame min-h-screen bg-[var(--docent-bg)] text-[var(--docent-fg)] antialiased">
    <div @if($aiEnabled)
         x-data="docentAssistant(@js($docent->route('ask')), @js($docent->route('ask.feedback')), @js($assistantStateNamespace), 'widget')"
         data-docent-assistant-enabled data-docent-assistant-state="{{ $assistantStateNamespace }}"
         @docent:assistant-open.window="openAssistant($event.detail)"
         @keydown.escape.window="assistantOpen && backFromAssistant()"
         @else
         x-data="{}"
         @endif
         @if($aiEnabled) :class="{ 'h-screen overflow-hidden': assistantOpen }" @endif
         class="isolate flex min-h-screen flex-col">
        <header class="docent-widget-header sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 px-3 py-3 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/95">
            <div class="flex items-center gap-2">
                @if($searchEnabled)
                    <div @if($aiEnabled) x-show="!assistantOpen" @endif x-data="docentWidgetSearch('{{ $docent->route('search', ['mode' => 'widget']) }}', @js($aiEnabled))"
                         @docent:widget-search.window="setQuery($event.detail.query)" class="relative min-w-0 flex-1">
                        <svg x-show="!loading" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <svg x-show="loading" x-cloak class="docent-search-spinner pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--docent-accent)]" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9" class="opacity-25"/><path d="M21 12a9 9 0 0 0-9-9"/></svg>
                        <input data-docent-widget-search x-ref="input" x-model="query" @input="onInput()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="enter()"
                               type="search" name="docent-widget-search" autocomplete="off" spellcheck="false" placeholder="Search help…" aria-label="Search documentation"
                               class="h-10 w-full rounded-xl bg-slate-50 py-2 pl-9 text-base text-slate-900 shadow-sm ring-1 ring-slate-950/10 placeholder:text-slate-400 focus:bg-white focus:outline-2 focus:-outline-offset-1 focus:outline-[var(--docent-accent)] dark:bg-slate-900 dark:text-white dark:shadow-none dark:ring-white/10 dark:focus:bg-slate-950 sm:text-sm {{ $aiEnabled ? 'pr-16' : 'pr-3' }}">

                        @if($aiEnabled)
                            <button x-show="canAsk()" x-cloak type="button" @click="handoff()" aria-label="Ask Assistant"
                                    class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-lg bg-slate-950/5 px-2 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-950/10 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[var(--docent-accent)] dark:bg-white/10 dark:text-slate-200 dark:hover:bg-white/15">
                                Ask
                            </button>
                        @endif

                        <div x-show="query.trim() !== ''" x-cloak class="docent-widget-results docent-scroll absolute inset-x-0 top-[calc(100%+0.5rem)] max-h-[min(23rem,60vh)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                            {{-- First-search placeholder only; refinements keep
                                 the previous outcome pinned while the input
                                 spinner signals the work. --}}
                            <div x-show="loading && !searched" role="status" class="px-3 py-7 text-center text-sm text-slate-400">Searching…</div>
                            <template x-for="(result, index) in results" :key="result.slug + '-' + index">
                                <a :href="href(result)" @click.prevent="go(result)" @mouseenter="selected = index" :data-selected="selected === index"
                                   class="block rounded-lg px-3 py-2.5 transition" :class="selected === index ? 'bg-[color-mix(in_srgb,var(--docent-accent)_12%,transparent)]' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'">
                                    <span class="block text-sm font-semibold text-slate-900 dark:text-white" x-text="result.heading || result.title"></span>
                                    <span x-show="result.group" class="mt-0.5 block text-[11px] font-semibold uppercase tracking-wide text-[var(--docent-accent)]" x-text="result.group"></span>
                                </a>
                            </template>
                            @if($aiEnabled)
                                <button x-show="searched && canAsk()" type="button" @click="handoff()" @mouseenter="selected = results.length" :data-selected="selected === results.length"
                                        class="mt-1.5 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition"
                                        :class="selected === results.length ? 'bg-[color-mix(in_srgb,var(--docent-accent)_12%,transparent)]' : 'bg-slate-50 hover:bg-slate-100 dark:bg-slate-800/40 dark:hover:bg-slate-800/70'">
                                    <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-[color-mix(in_srgb,var(--docent-accent)_12%,transparent)] text-[var(--docent-accent)] [&_svg]:size-4" aria-hidden="true">{!! \STS\Docent\Support\Icon::svg('sparkles') !!}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">Ask Assistant about “<span x-text="query"></span>”</span>
                                        <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Get an answer from these docs.</span>
                                    </span>
                                </button>
                            @endif
                            <div x-show="searched && results.length === 0" class="px-3 py-7 text-center text-sm text-slate-400">No matching help pages.</div>
                        </div>
                    </div>
                @endif

                @if($aiEnabled)
                    <div x-show="assistantOpen" x-cloak class="flex min-w-0 flex-1 items-center gap-2">
                        <button type="button" @click="backFromAssistant()" aria-label="Back to help"
                                class="relative inline-flex size-10 shrink-0 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                            <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
                            <span class="[&_svg]:size-4" aria-hidden="true">{!! \STS\Docent\Support\Icon::svg('arrow-left') !!}</span>
                        </button>
                        <p id="docent-assistant-title-widget" class="min-w-0 flex-1 truncate text-base font-semibold text-slate-950 dark:text-white sm:text-sm">Assistant</p>
                        <button x-show="messages.length > 0" x-cloak type="button" @click="newConversation()" aria-label="Start a new conversation"
                                class="relative inline-flex size-10 shrink-0 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                            <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
                            <span class="[&_svg]:size-4" aria-hidden="true">{!! \STS\Docent\Support\Icon::svg('trash') !!}</span>
                        </button>
                    </div>

                    <button x-show="!assistantOpen" x-cloak type="button" @click="$dispatch('docent:assistant-open')" aria-label="Open Assistant" title="Open Assistant"
                            class="relative inline-flex size-10 shrink-0 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                        <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
                        <span class="shrink-0 [&_svg]:size-4" aria-hidden="true">{!! \STS\Docent\Support\Icon::svg('chat-bubble-left-right') !!}</span>
                    </button>
                @endif

                <a href="{{ $fullDocsUrl }}" target="_top" aria-label="Open full docs" title="Open full docs"
                   class="relative inline-flex size-10 shrink-0 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                    <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
                    <span class="shrink-0 [&_svg]:size-4" aria-hidden="true">{!! \STS\Docent\Support\Icon::svg('book-open') !!}</span>
                </a>

                <button type="button" data-docent-widget-close aria-label="Close help" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                    <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </header>

        <main id="docent-content" class="flex min-h-0 flex-1 flex-col">
            <div @if($aiEnabled) x-show="!assistantOpen" @endif class="min-h-0 flex-1">
                @yield('content')
            </div>

            @if($aiEnabled)
                <section x-show="assistantOpen" x-cloak x-ref="assistantPanel" data-docent-assistant-panel role="region" aria-labelledby="docent-assistant-title-widget"
                         class="flex min-h-0 flex-1 flex-col">
                    @include('docent::partials.assistant-content', ['widget' => true])
                </section>
            @endif
        </main>
    </div>
</body>
</html>
