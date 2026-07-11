<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="docent-scroll">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($title ?? null) ? $title.' — '.$siteName : $siteName }}</title>
    @isset($description)
        @if($description)<meta name="description" content="{{ $description }}">@endif
    @endisset

    <style>:root{--docent-accent:{{ $docent->accent() }};}</style>
    <script>(function(){try{var t=localStorage.getItem('docentTheme');var d=t?t==='dark':window.matchMedia('(prefers-color-scheme: dark)').matches;document.documentElement.classList.toggle('dark',d);}catch(e){}})();</script>

    <link rel="stylesheet" href="{{ $docent->asset('docent.css') }}">
    <script defer src="{{ $docent->asset('docent.js') }}"></script>
</head>
<body class="min-h-screen bg-[var(--docent-bg)] text-[var(--docent-fg)] antialiased">
    <a href="#docent-content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-[var(--docent-accent)] focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white">
        Skip to content
    </a>

    <div x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false">
        {{-- Top bar --}}
        <header class="docent-topbar sticky top-0 z-40 h-16 border-b border-slate-200/80 bg-white/80 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/80">
            <div class="mx-auto flex h-full max-w-[100rem] items-center gap-3 px-4 sm:px-6">
                <button type="button" class="-ml-1 inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900 lg:hidden dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                        @click="sidebar = true" aria-label="Open navigation" :aria-expanded="sidebar" aria-controls="docent-sidebar">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>

                <a href="{{ $homeUrl }}" class="flex items-center gap-2 font-semibold tracking-tight text-slate-900 dark:text-white">
                    @if($docent->logo())
                        <img src="{{ $docent->logo() }}" alt="{{ $siteName }}" class="h-7 w-auto">
                    @else
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-[var(--docent-accent)] text-sm font-bold text-white">{{ mb_substr($siteName, 0, 1) }}</span>
                        <span class="text-[15px]">{{ $siteName }}</span>
                    @endif
                </a>

                <div class="ml-auto flex items-center gap-2">
                    @if($searchEnabled)
                        <button type="button" @click="$dispatch('docent:search-open')" aria-label="Search documentation"
                                class="group inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-400 transition hover:border-slate-300 hover:text-slate-500 sm:w-64 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <span class="hidden sm:inline">Search docs…</span>
                            <kbd class="ml-auto hidden rounded border border-slate-200 bg-white px-1.5 py-0.5 font-sans text-[11px] font-medium text-slate-400 sm:inline dark:border-slate-700 dark:bg-slate-800" data-docent-kbd>⌘K</kbd>
                        </button>
                    @endif

                    <button type="button" @click="$store.theme.toggle()" aria-label="Toggle dark mode"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                        <svg x-show="!$store.theme.dark" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        <svg x-show="$store.theme.dark" x-cloak viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>
                </div>
            </div>
        </header>

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
                    @include('docent::partials.navigation')
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

        @if($searchEnabled)
            @include('docent::partials.search')
        @endif
    </div>
</body>
</html>
