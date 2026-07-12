<template x-teleport="body">
    <div x-data="docentSearch('{{ route('docent.search') }}')"
         @docent:search-open.window="show()"
         x-show="open" x-cloak
         class="fixed inset-0 z-[60]" role="dialog" aria-modal="true" aria-label="Search documentation">

        <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" @click="hide()"></div>

        <div class="absolute inset-x-0 top-[15vh] mx-auto w-full max-w-xl px-4">
            <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900"
                 @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="enter()" @keydown.escape.prevent="hide()">

                <div class="flex items-center gap-3 border-b border-slate-200 px-4 dark:border-slate-800">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-400"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input x-ref="input" x-model="query" @input="onInput()" type="text" autocomplete="off" spellcheck="false"
                           placeholder="Search documentation…"
                           class="w-full bg-transparent py-4 text-[15px] text-slate-900 placeholder:text-slate-400 focus:outline-none dark:text-white">
                    <kbd class="rounded border border-slate-200 px-1.5 py-0.5 text-[11px] font-medium text-slate-400 dark:border-slate-700">Esc</kbd>
                </div>

                <div x-ref="list" class="docent-scroll max-h-[60vh] overflow-y-auto p-2">
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

                    {{-- Empty --}}
                    <div x-show="searched && !loading && results.length === 0" class="px-3 py-8 text-center text-sm text-slate-400">
                        No results for “<span x-text="query"></span>”
                    </div>

                    {{-- Initial hint --}}
                    <div x-show="!searched && !loading" class="px-3 py-8 text-center text-sm text-slate-400">
                        Type to search the documentation.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
