@php($docent = \STS\Docent\Facades\Docent::getFacadeRoot())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="docent-scroll">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $docent->siteName() }} — Admin</title>

    @if($docent->favicon())
        <link rel="icon" href="{{ $docent->favicon() }}">
    @endif
    @if($docent->fontHref())
        <link rel="preconnect" href="{{ $docent->fontHref() }}" crossorigin>
        <link rel="stylesheet" href="{{ $docent->fontHref() }}">
    @endif

    {{-- FOUC-free dark mode: same blocking script and storage key as the reader. --}}
    <script>(function(){try{var t=localStorage.getItem('docentTheme');var d=t?t==='dark':window.matchMedia('(prefers-color-scheme: dark)').matches;document.documentElement.classList.toggle('dark',d);}catch(e){}})();</script>

    <link rel="stylesheet" href="{{ $docent->asset('docent-admin.css') }}">
    <script defer src="{{ $docent->asset('docent-admin.js') }}"></script>

    {{-- Dynamic theme tokens last so host config always wins the cascade. --}}
    <style>{!! $docent->themeStyles() !!}</style>
</head>
<body class="h-screen overflow-hidden bg-[var(--docent-bg)] text-[var(--docent-fg)] antialiased">
    <div id="docent-admin" x-cloak x-data="docentAdmin({
            base: @js(rtrim(route('docent.admin'), '/')),
            docsHome: @js($docent->url('')),
            csrf: @js(csrf_token()),
        })" class="flex h-screen flex-col">

        {{-- ================= Top bar ================= --}}
        <header class="flex h-14 flex-none items-center gap-3 border-b border-[var(--docent-border)] px-4">
            <button type="button" @click="treeOverlay = true" aria-label="Open page tree"
                    class="dax-btn dax-btn-ghost dax-btn-icon lg:hidden">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>

            <a :href="docsHome" class="inline-flex items-center gap-1.5 text-sm font-medium text-[var(--docent-muted)] transition hover:text-[var(--docent-fg)]">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Back to docs
            </a>

            <div class="mx-1 h-5 w-px bg-[var(--docent-border)]"></div>

            <div class="flex items-center gap-2">
                @if($docent->logomark())
                    <img src="{{ $docent->logomark() }}" alt="{{ $docent->siteName() }}" class="h-6 w-6">
                @elseif($docent->logo())
                    <img src="{{ $docent->logo() }}" alt="{{ $docent->siteName() }}" class="h-6 w-auto{{ $docent->logoDark() ? ' dark:hidden' : '' }}">
                    @if($docent->logoDark())
                        <img src="{{ $docent->logoDark() }}" alt="{{ $docent->siteName() }}" class="hidden h-6 w-auto dark:block">
                    @endif
                @else
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-[var(--docent-accent)] text-xs font-bold text-white">{{ mb_substr($docent->siteName(), 0, 1) }}</span>
                @endif
                <span class="font-semibold tracking-tight text-[var(--docent-strong)]">{{ $docent->siteName() }}</span>
                <span class="rounded-md border border-[var(--docent-border)] bg-[var(--docent-panel)] px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-[var(--docent-faint)]">Admin</span>
            </div>

            <button type="button" @click="$store.theme.toggle()" aria-label="Toggle dark mode"
                    class="dax-btn dax-btn-ghost dax-btn-icon ml-auto">
                <svg x-show="!$store.theme.dark" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                <svg x-show="$store.theme.dark" x-cloak viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
        </header>

        {{-- ================= Body ================= --}}
        <div class="flex min-h-0 flex-1 flex-col lg:flex-row">

            {{-- --------- Left pane: tree --------- --}}
            <aside class="hidden w-[300px] flex-none flex-col border-r border-[var(--docent-border)] bg-[var(--docent-panel)]/40 lg:flex">
                @include('docent::partials.admin.tree')
            </aside>

            {{-- Mobile tree overlay --}}
            <div x-show="treeOverlay" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
                <div x-show="treeOverlay" x-transition.opacity class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm" @click="treeOverlay = false"></div>
                <div x-show="treeOverlay"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                     class="absolute inset-y-0 left-0 flex w-80 max-w-[85%] flex-col bg-[var(--docent-bg)] shadow-xl">
                    @include('docent::partials.admin.tree', ['overlay' => true])
                </div>
            </div>

            {{-- --------- Center pane: editor --------- --}}
            <main class="flex min-h-0 min-w-0 flex-1 flex-col border-r border-[var(--docent-border)]">
                {{-- Empty state --}}
                <div x-show="!hasSelection && !detailLoading" class="flex flex-1 flex-col items-center justify-center gap-3 p-10 text-center">
                    <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-[var(--docent-panel)] text-[var(--docent-faint)]">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <p class="text-sm font-medium text-[var(--docent-fg)]">Select a page to edit</p>
                    <p class="max-w-xs text-sm text-[var(--docent-faint)]">Choose a page from the tree, or create a new one to start authoring.</p>
                </div>

                {{-- Detail loading skeleton --}}
                <div x-show="detailLoading" class="flex-1 space-y-4 p-8">
                    <div class="dax-skeleton h-9 w-2/3"></div>
                    <div class="dax-skeleton h-4 w-1/3"></div>
                    <div class="dax-skeleton h-72 w-full"></div>
                </div>

                {{-- Editor --}}
                <div x-show="hasSelection && !detailLoading" class="flex min-h-0 flex-1 flex-col">
                    @include('docent::partials.admin.editor')
                </div>
            </main>

            {{-- --------- Right pane: live preview --------- --}}
            @include('docent::partials.admin.preview')
        </div>

        {{-- ================= Revisions slide-over ================= --}}
        @include('docent::partials.admin.revisions')

        {{-- ================= Toasts ================= --}}
        <div class="pointer-events-none fixed right-4 top-4 z-[60] flex flex-col gap-2">
            <template x-for="t in toasts" :key="t.id">
                <div class="dax-toast pointer-events-auto"
                     :class="{ 'dax-toast-error': t.type === 'error', 'dax-toast-success': t.type === 'success' }"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-4 opacity-0" x-transition:enter-end="translate-x-0 opacity-100">
                    <span class="dax-toast-dot"></span>
                    <span class="flex-1" x-text="t.message"></span>
                    <button type="button" @click="dismissToast(t.id)" class="text-[var(--docent-faint)] hover:text-[var(--docent-fg)]" aria-label="Dismiss">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </template>
        </div>
    </div>
</body>
</html>
