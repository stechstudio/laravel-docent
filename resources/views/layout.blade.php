<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="docent-scroll">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? null) && ! str_contains($siteName, $title) ? $title.' — '.$siteName : $siteName }}</title>
    @isset($description)
        @if($description)<meta name="description" content="{{ $description }}">@endif
    @endisset

    @if($docent->favicon())
        <link rel="icon" href="{{ $docent->favicon() }}">
    @endif
    @if($docent->fontHref())
        <link rel="preconnect" href="{{ $docent->fontHref() }}" crossorigin>
        <link rel="stylesheet" href="{{ $docent->fontHref() }}">
    @endif

    <script>(function(){try{var t=localStorage.getItem('docentTheme');var d=t?t==='dark':window.matchMedia('(prefers-color-scheme: dark)').matches;document.documentElement.classList.toggle('dark',d);}catch(e){}})();</script>

    <link rel="stylesheet" href="{{ $docent->asset('docent.css') }}">
    <script defer src="{{ $docent->asset('docent.js') }}"></script>

    {{-- Dynamic theme tokens last so host config always wins the cascade. --}}
    <style>{!! $docent->themeStyles() !!}</style>
</head>
<body data-docent-slug="{{ $currentSlug ?? '' }}" class="min-h-screen bg-[var(--docent-bg)] text-[var(--docent-fg)] antialiased">
    @php($aiEnabled = (bool) config('docent.ai.enabled', false))
    <a href="#docent-content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-[var(--docent-accent)] focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white">
        Skip to content
    </a>

    @if($aiEnabled)
    <div x-data="docentAssistant(@js(route('docent.ask')), @js(route('docent.ask.feedback')), @js($assistantStateNamespace), 'reader')"
         data-docent-assistant-enabled data-docent-assistant-state="{{ $assistantStateNamespace }}"
         @docent:assistant-open.window="openAssistant($event.detail)"
         @docent:surface-closed.window="syncBodyLock()"
         @keydown.escape.window="escape($event)"
         :class="{ 'docent-assistant-is-open': assistantOpen, 'docent-assistant-is-expanded': assistantOpen && assistantExpanded }">
    @endif
    <div x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false" class="isolate">
        {{-- Top bar --}}
        <header class="docent-topbar sticky top-0 z-40 h-16 border-b border-slate-200/80 bg-white/80 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/80">
            <div class="mx-auto flex h-full max-w-[100rem] items-center gap-3 px-4 sm:px-6">
                @unless($landing ?? false)
                    <button type="button" class="-ml-1 inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900 lg:hidden dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                            @click="sidebar = true" aria-label="Open navigation" :aria-expanded="sidebar" aria-controls="docent-sidebar">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                @endunless

                <a href="{{ $homeUrl }}" class="flex items-center gap-2 font-semibold tracking-tight text-slate-900 dark:text-white">
                    @if($docent->logomark())
                        {{-- Compact square mark, only below the sm breakpoint. --}}
                        <img src="{{ $docent->logomark() }}" alt="{{ $siteName }}" class="h-7 w-7 sm:hidden">
                    @endif

                    <span class="flex items-center gap-2{{ $docent->logomark() ? ' hidden sm:flex' : '' }}">
                        @if($docent->logo())
                            <img src="{{ $docent->logo() }}" alt="{{ $siteName }}" class="h-7 w-auto{{ $docent->logoDark() ? ' dark:hidden' : '' }}">
                            @if($docent->logoDark())
                                <img src="{{ $docent->logoDark() }}" alt="{{ $siteName }}" class="hidden h-7 w-auto dark:block">
                            @endif
                        @else
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-[var(--docent-accent)] text-sm font-bold text-white">{{ mb_substr($siteName, 0, 1) }}</span>
                            <span class="text-[15px]">{{ $siteName }}</span>
                        @endif
                    </span>
                </a>

                {{-- Overridable topbar regions: a layout may replace either
                     with @section('topbar-nav') / @section('topbar-actions')
                     — an empty section suppresses the region entirely, which
                     is why this checks the env method (section defined at
                     all) rather than @hasSection (non-empty content). --}}
                @if($__env->hasSection('topbar-nav'))
                    @yield('topbar-nav')
                @else
                    @include('docent::partials.topbar-nav')
                @endif

                <div class="ml-auto flex items-center gap-2">
                    @if($__env->hasSection('topbar-actions'))
                        @yield('topbar-actions')
                    @else
                        @include('docent::partials.topbar-actions')
                    @endif

                    <button type="button" @click="$store.theme.toggle()" aria-label="Toggle dark mode"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                        <svg x-show="!$store.theme.dark" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        <svg x-show="$store.theme.dark" x-cloak viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>
                </div>
            </div>
        </header>

        @if($landing ?? false)
        <div class="mx-auto max-w-[100rem] px-4 sm:px-6">
            <main id="docent-content" class="docent-main min-w-0 px-0 py-12 lg:py-16">
                @yield('content')
            </main>
        </div>
        @else
        <div class="mx-auto flex max-w-[100rem] px-4 sm:px-6">
            {{-- Left sidebar (desktop) --}}
            <aside class="docent-sidebar docent-scroll sticky top-16 hidden h-[calc(100vh-4rem)] w-64 shrink-0 overflow-y-auto py-8 pr-6 lg:block">
                @include('docent::partials.navigation')
            </aside>

            {{-- Mobile sidebar drawer --}}
            <div x-show="sidebar" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
                <div x-show="sidebar" x-transition.opacity class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm" @click="sidebar = false"></div>
                <aside id="docent-sidebar" x-show="sidebar"
                       x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                       x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                       class="docent-scroll absolute inset-y-0 left-0 w-72 max-w-[80%] overflow-y-auto bg-white p-6 shadow-xl dark:bg-slate-950">
                    <div class="mb-6 flex items-center justify-between">
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $siteName }}</span>
                        <button type="button" @click="sidebar = false" aria-label="Close navigation" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    @include('docent::partials.section-switcher')
                    @if(count($sections ?? []) > 1)
                        <div class="h-6"></div>
                    @endif
                    {{-- The drawer replaces the top bar entirely, so its utility links join the pinned list here. --}}
                    @include('docent::partials.navigation', ['navigationLinks' => array_merge($topbarLinks ?? [], $navigationLinks ?? [])])
                </aside>
            </div>

            {{-- Content + right rail --}}
            <div class="flex min-w-0 flex-1">
                <main id="docent-content" class="docent-main min-w-0 flex-1 px-0 py-10 lg:px-10 xl:px-12">
                    @yield('content')
                </main>

                @yield('rail')
            </div>
        </div>
        @endif

        @if($searchEnabled)
            @include('docent::partials.search')
        @endif
    </div>
    @if($aiEnabled)
        @include('docent::partials.assistant-panel')
    </div>
    @endif
</body>
</html>
