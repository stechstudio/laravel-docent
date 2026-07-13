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
            <button type="button" @click="toggleSidebar()" aria-label="Toggle page tree" :aria-expanded="sidebar"
                    class="dax-btn dax-btn-ghost dax-btn-icon -ml-1" :class="sidebar ? '' : 'text-[var(--docent-accent)]'">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
            </button>

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
                <span class="rounded-md border border-[var(--docent-border)] bg-[var(--docent-panel)] px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-[var(--docent-faint)]">Admin</span>
            </div>

            <div class="ml-auto flex items-center gap-1">
                <a :href="docsHome" class="dax-btn dax-btn-ghost gap-1.5 text-[13px]" target="_blank" rel="noopener">
                    View docs
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                </a>
                <button type="button" @click="$store.theme.toggle()" aria-label="Toggle dark mode"
                        class="dax-btn dax-btn-ghost dax-btn-icon">
                    <svg x-show="!$store.theme.dark" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg x-show="$store.theme.dark" x-cloak viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
            </div>
        </header>

        {{-- ================= Body ================= --}}
        <div class="flex min-h-0 flex-1">

            {{-- Left: page tree (collapsible on wide screens, overlay on small) --}}
            <aside x-show="sidebar" x-cloak
                   class="dax-sidebar hidden w-[280px] flex-none flex-col border-r border-[var(--docent-border)] bg-[var(--docent-panel)]/40 md:flex">
                @include('docent::partials.admin.tree')
            </aside>

            {{-- Small-screen tree overlay --}}
            <div x-show="sidebar && overlayMode" x-cloak class="fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true">
                <div x-show="sidebar" x-transition.opacity class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm" @click="sidebar = false"></div>
                <div x-show="sidebar"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                     class="absolute inset-y-0 left-0 flex w-80 max-w-[85%] flex-col bg-[var(--docent-bg)] shadow-xl">
                    @include('docent::partials.admin.tree', ['overlay' => true])
                </div>
            </div>

            {{-- Main column --}}
            <main class="flex min-h-0 min-w-0 flex-1 flex-col">

                {{-- Empty state --}}
                <div x-show="!hasSelection && !detailLoading" class="flex flex-1 flex-col items-center justify-center gap-3 p-10 text-center">
                    <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-[var(--docent-panel)] text-[var(--docent-faint)]">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <p class="text-sm font-medium text-[var(--docent-fg)]">Select a page to edit</p>
                    <p class="max-w-xs text-sm text-[var(--docent-faint)]">Choose a page from the tree, or create a new one to start authoring.</p>
                </div>

                {{-- Detail loading skeleton --}}
                <div x-show="detailLoading" class="mx-auto w-full max-w-3xl flex-1 space-y-4 p-10">
                    <div class="dax-skeleton h-9 w-2/3"></div>
                    <div class="dax-skeleton h-4 w-1/3"></div>
                    <div class="dax-skeleton h-72 w-full"></div>
                </div>

                {{-- Document workspace --}}
                <div x-show="hasSelection && !detailLoading" class="flex min-h-0 flex-1">

                    {{-- Center: write / preview --}}
                    <div class="flex min-h-0 min-w-0 flex-1 flex-col">

                        {{-- Header row: view tabs + save actions --}}
                        <div class="flex h-12 flex-none items-center gap-2 border-b border-[var(--docent-border)] px-4 sm:px-6">
                            <div class="dax-tabs" role="tablist" aria-label="Editor view">
                                <button type="button" class="dax-tab" :class="view === 'write' ? 'is-active' : ''" role="tab" :aria-selected="view === 'write'"
                                        @click="setView('write')">Write</button>
                                <button type="button" class="dax-tab" :class="view === 'preview' ? 'is-active' : ''" role="tab" :aria-selected="view === 'preview'"
                                        @click="setView('preview')">
                                    Preview
                                    <span x-show="previewIssues.length" x-cloak class="dax-tab-badge" x-text="previewIssues.length"></span>
                                </button>
                            </div>

                            <span x-show="previewLoading" x-cloak class="inline-flex items-center gap-1.5 text-xs text-[var(--docent-faint)]">
                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" class="animate-spin"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg>
                            </span>

                            <span x-show="locked" x-cloak class="inline-flex items-center gap-1.5 rounded-md border border-[var(--docent-border)] bg-[var(--docent-panel)] px-2 py-1 text-[11px] font-semibold text-[var(--docent-muted)]">
                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                Locked
                            </span>

                            <div class="ml-auto flex items-center gap-2.5">
                                <span x-show="dirty && !readonly" class="inline-flex items-center gap-1.5 text-xs text-[var(--docent-muted)]">
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>Unsaved
                                </span>
                                <span x-show="!dirty && lastSaved && !readonly" x-cloak class="text-xs text-[var(--docent-faint)]" x-text="'Saved ' + relativeTime(lastSaved)"></span>

                                <template x-if="readonly && !locked">
                                    <button type="button" class="dax-btn dax-btn-primary text-[13px]" @click="editOverridePrompt()">Edit this page</button>
                                </template>
                                <template x-if="!readonly">
                                    <div class="flex items-center gap-1.5">
                                        <button type="button" class="dax-btn text-[13px]" :disabled="!canSave" @click="save()">
                                            <span x-show="!saving">Save draft</span>
                                            <span x-show="saving" x-cloak>Saving…</span>
                                        </button>
                                        <template x-if="!creating">
                                            <button type="button" class="dax-btn dax-btn-primary text-[13px]" :disabled="publishing"
                                                    @click="published ? unpublish() : publish()"
                                                    x-text="published ? 'Unpublish' : 'Publish'"></button>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Read-only notice (repository pages) --}}
                        <div x-show="readonly && !locked" x-cloak class="flex-none border-b border-[var(--docent-border)] bg-[var(--docent-panel)]/60 px-4 py-2 sm:px-6">
                            <p class="flex items-center gap-2 text-[13px] text-[var(--docent-muted)]">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                This page lives in your repository and is read-only here. <button type="button" class="dax-link" @click="editOverridePrompt()">Edit a copy</button>
                            </p>
                        </div>

                        <div x-show="locked" x-cloak class="flex-none border-b border-[var(--docent-border)] bg-[var(--docent-panel)]/60 px-4 py-2 sm:px-6">
                            <p class="flex items-center gap-2 text-[13px] text-[var(--docent-muted)]">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                This page is maintained in the repository and can't be edited here.
                            </p>
                        </div>

                        {{-- Shadowing notice (database page overriding a file) --}}
                        <div x-show="!readonly && shadowed" x-cloak class="flex-none border-b border-amber-500/30 bg-amber-500/10 px-4 py-2 sm:px-6">
                            <p class="flex items-center gap-2 text-[13px] text-[var(--docent-muted)]">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-none text-amber-500"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                This database copy overrides a repository file — readers see this version.
                                <button type="button" class="dax-link" @click="removePage(true)">Discard and restore the file</button>
                            </p>
                        </div>

                        {{-- Write --}}
                        <div x-show="view === 'write'" class="docent-scroll min-h-0 flex-1 overflow-y-auto">
                            @include('docent::partials.admin.editor')
                        </div>

                        {{-- Preview --}}
                        <div x-show="view === 'preview'" x-cloak class="docent-scroll min-h-0 flex-1 overflow-y-auto bg-[var(--docent-panel)]/20">
                            @include('docent::partials.admin.preview')
                        </div>

                        {{-- Save-time reference issues --}}
                        <div x-show="!readonly && saveIssues.length" x-cloak class="flex-none space-y-1.5 border-t border-[var(--docent-border)] px-6 py-3">
                            <template x-for="(issue, i) in saveIssues" :key="i">
                                <div class="dax-issue">
                                    <span x-show="issue.line" class="dax-issue-line" x-text="'L' + issue.line"></span>
                                    <span class="flex-1" x-text="issue.message"></span>
                                    <button type="button" @click="saveIssues.splice(i, 1)" class="text-[var(--docent-faint)] hover:text-[var(--docent-fg)]" aria-label="Dismiss">
                                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Right: page settings rail --}}
                    @include('docent::partials.admin.rail')
                </div>
            </main>
        </div>

        {{-- ================= Revisions slide-over ================= --}}
        @include('docent::partials.admin.revisions')

        {{-- ================= Group settings modal ================= --}}
        @include('docent::partials.admin.group-settings')

        {{-- ================= Edit-a-copy confirmation ================= --}}
        <div x-show="overridePromptOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="overridePromptOpen = false">
            <div x-show="overridePromptOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/45 backdrop-blur-sm" @click="overridePromptOpen = false"></div>
            <div x-show="overridePromptOpen"
                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                 class="dax-modal relative w-full max-w-md p-5">
                <p class="text-sm font-semibold text-[var(--docent-strong)]">Edit this page?</p>
                <p class="mt-2 text-[13px] leading-relaxed text-[var(--docent-muted)]">
                    This page comes from a markdown file in your repository. Editing creates a
                    <strong class="font-medium text-[var(--docent-fg)]">database copy</strong> that readers will see instead of the file.
                    The file itself is untouched, and you can discard the copy at any time to restore it.
                </p>
                <div class="mt-4 flex items-center justify-end gap-2">
                    <button type="button" class="dax-btn dax-btn-ghost text-[13px]" @click="overridePromptOpen = false">Cancel</button>
                    <button type="button" class="dax-btn dax-btn-primary text-[13px]" @click="override()">Create editable copy</button>
                </div>
            </div>
        </div>

        {{-- ================= View markdown modal ================= --}}
        <div x-show="markdownOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="markdownOpen = false">
            <div x-show="markdownOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/45 backdrop-blur-sm" @click="markdownOpen = false"></div>
            <div x-show="markdownOpen"
                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                 class="dax-modal relative flex max-h-[80vh] w-full max-w-2xl flex-col">
                <div class="flex flex-none items-center gap-2 border-b border-[var(--docent-border)] px-5 py-3">
                    <span class="text-sm font-semibold text-[var(--docent-strong)]">Markdown export</span>
                    <button type="button" class="dax-btn dax-btn-ghost dax-btn-icon ml-auto" @click="markdownOpen = false" aria-label="Close">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="docent-scroll min-h-0 flex-1 overflow-y-auto p-5">
                    <div x-show="markdownLoading" class="dax-skeleton h-40 w-full"></div>
                    <pre x-show="!markdownLoading" class="dax-md-export"><code x-text="markdownText"></code></pre>
                </div>
                <div class="flex flex-none items-center gap-3 border-t border-[var(--docent-border)] px-5 py-3">
                    <button type="button" class="dax-btn dax-btn-primary text-xs" @click="copyMarkdown()">Copy</button>
                    <p class="text-xs text-[var(--docent-faint)]">Normalized markdown export — the visual document is the source of truth.</p>
                </div>
            </div>
        </div>

        {{-- ================= Toasts ================= --}}
        <div class="pointer-events-none fixed right-4 top-4 z-[80] flex flex-col gap-2">
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
