@php($aiEnabled = (bool) config('docent.ai.enabled', false))
<template x-teleport="body">
    <div x-data="docentSearch('{{ route('docent.search') }}', @js($aiEnabled))"
         @docent:search-open.window="show()"
         @docent:assistant-open.window="open && hide(false)"
         @keydown.escape.window="open && hide()"
         x-show="open" x-cloak
         data-docent-search-dialog class="fixed inset-0 z-[60]" role="dialog" aria-modal="true" aria-label="Search documentation">

        <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" @click="hide()"></div>

        <div class="absolute inset-x-0 top-[15vh] mx-auto w-full max-w-xl px-4">
            <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900"
                 @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="enter()"
                 @keydown.meta.enter.prevent="handoff()" @keydown.ctrl.enter.prevent="handoff()" @keydown.tab="trap($event)">

                <div class="flex items-center gap-3 border-b border-slate-200 px-4 dark:border-slate-800">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-400"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input x-ref="input" x-model="query" @input="onInput()" type="text" name="docent-search" autocomplete="off" spellcheck="false"
                           placeholder="Search documentation…" aria-label="Search documentation"
                           class="w-full bg-transparent py-4 text-[15px] text-slate-900 placeholder:text-slate-400 focus:outline-none dark:text-white">
                    @if($aiEnabled)
                        <button type="button" @click="handoff()" :disabled="!canAsk()"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-slate-950/5 py-2 pl-2.5 pr-2 text-sm font-medium text-slate-700 hover:bg-slate-950/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] disabled:cursor-not-allowed disabled:opacity-40 dark:bg-white/10 dark:text-slate-200 dark:hover:bg-white/15">
                            Ask Assistant
                            <kbd data-docent-ask-kbd class="rounded bg-white/80 px-1.5 py-0.5 font-sans text-[0.6875rem] text-slate-500 dark:bg-slate-950/50 dark:text-slate-400">⌘↵</kbd>
                        </button>
                    @endif
                    <kbd class="rounded border border-slate-200 px-1.5 py-0.5 text-[11px] font-medium text-slate-500 dark:border-slate-700 dark:text-slate-400">Esc</kbd>
                </div>

                <div x-ref="list" class="docent-scroll max-h-[60vh] overflow-y-auto">
                    <div class="p-2">
                    {{-- Loading --}}
                    <div x-show="loading" class="px-3 py-8 text-center text-sm text-slate-400">Searching…</div>

                    {{-- Results --}}
                    <template x-for="(r, i) in results" :key="r.slug + '-' + i">
                        <a :href="r.anchor ? r.url + '#' + r.anchor : r.url" @click.prevent="go(r)" @mouseenter="selected = i"
                           :data-selected="selected === i"
                           class="block rounded-lg px-3 py-2.5 transition"
                           :class="selected === i ? 'bg-[color-mix(in_srgb,var(--docent-accent)_12%,transparent)]' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'">
                            <div class="flex items-baseline gap-2">
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-[var(--docent-accent)]" x-show="r.group" x-text="r.group"></span>
                                <span class="text-sm font-medium text-slate-900 dark:text-white" x-text="r.heading || r.title"></span>
                            </div>
                            <p class="docent-snippet mt-0.5 line-clamp-2 text-sm text-slate-500 dark:text-slate-400" x-show="r.snippet" x-html="r.snippet"></p>
                        </a>
                    </template>

                    @if($aiEnabled)
                        <button x-show="canAsk()" type="button" @click="handoff()" @mouseenter="selected = results.length"
                                :data-selected="selected === results.length"
                                class="mt-1 block w-full border-t border-slate-100 px-3 py-3 text-left transition dark:border-slate-800"
                                :class="selected === results.length ? 'bg-[color-mix(in_srgb,var(--docent-accent)_10%,transparent)]' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'">
                            <span class="block text-sm font-semibold text-slate-900 dark:text-white">Ask Assistant about “<span x-text="query"></span>”</span>
                            <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Get an answer from these docs.</span>
                        </button>
                    @endif

                    {{-- Empty --}}
                    <div x-show="searched && !loading && results.length === 0" class="px-3 py-8 text-center text-sm text-slate-400">
                        No results for “<span x-text="query"></span>”
                    </div>

                    {{-- Initial hint --}}
                    <div x-show="!searched && !loading" class="px-3 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        Type to search the documentation.
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
