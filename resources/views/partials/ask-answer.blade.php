<section x-show="askMode" x-cloak class="{{ ($compact ?? false) ? 'p-3' : 'p-4' }}" aria-live="polite">
    <button type="button" @click="backToResults()"
            class="mb-3 inline-flex items-center gap-1 text-xs font-semibold text-slate-500 transition hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Back to results
    </button>

    <div class="rounded-xl border border-slate-200 bg-slate-50/70 {{ ($compact ?? false) ? 'p-3' : 'p-4' }} dark:border-slate-800 dark:bg-slate-950/50">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">From the docs</p>

        <div x-show="asking && answer === '' && !askError" class="mt-3 space-y-2" aria-label="Reading the documentation">
            <span class="block h-2.5 w-11/12 animate-pulse rounded bg-slate-200 dark:bg-slate-800"></span>
            <span class="block h-2.5 w-4/5 animate-pulse rounded bg-slate-200 dark:bg-slate-800"></span>
            <span class="block h-2.5 w-2/3 animate-pulse rounded bg-slate-200 dark:bg-slate-800"></span>
        </div>

        <p x-show="answer !== ''" x-text="displayAnswer()"
           class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200"></p>

        <p x-show="askError" x-text="askError" class="mt-2 text-sm leading-6 text-rose-600 dark:text-rose-400"></p>

        <div x-show="citedPages().length > 0" class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-800">
            <p class="mb-2 text-[11px] font-medium text-slate-400">Sources</p>
            <div class="flex flex-wrap gap-1.5">
                <template x-for="citation in citedPages()" :key="citation.slug">
                    <a :href="citation.url" class="inline-flex max-w-full items-center gap-1 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:border-[var(--docent-accent)] hover:text-[var(--docent-accent)] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                        <span class="truncate" x-text="citation.title"></span>
                        <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                </template>
            </div>
        </div>

        <div x-show="!asking && answer !== '' && questionId" class="mt-3 flex items-center gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
            <span class="text-xs text-slate-400" x-text="feedback ? 'Thanks for the feedback.' : 'Was this useful?' "></span>
            <div x-show="!feedback" class="ml-auto flex gap-1">
                <button type="button" @click="sendFeedback('up')" aria-label="Helpful answer"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-[var(--docent-accent)] hover:text-[var(--docent-accent)] dark:border-slate-700 dark:text-slate-400">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2h0a3.13 3.13 0 0 1 3 3.88Z"/></svg>
                </button>
                <button type="button" @click="sendFeedback('down')" aria-label="Not helpful answer"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-rose-400 hover:text-rose-500 dark:border-slate-700 dark:text-slate-400">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22h0a3.13 3.13 0 0 1-3-3.88Z"/></svg>
                </button>
            </div>
        </div>
    </div>
</section>
