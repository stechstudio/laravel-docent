{{-- Revision history slide-over --}}
<div x-show="revisionsOpen" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Revision history"
     @keydown.escape.window="revisionsOpen = false">
    <div x-show="revisionsOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm" @click="revisionsOpen = false"></div>

    <div x-show="revisionsOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="absolute inset-y-0 right-0 flex w-[26rem] max-w-[90%] flex-col border-l border-[var(--docent-border)] bg-[var(--docent-bg)] shadow-xl">

        <div class="flex h-14 flex-none items-center justify-between border-b border-[var(--docent-border)] px-4">
            <div>
                <p class="text-sm font-semibold text-[var(--docent-strong)]">Revisions</p>
                <p class="text-xs text-[var(--docent-faint)]" x-text="slug"></p>
            </div>
            <button type="button" @click="revisionsOpen = false" aria-label="Close revisions" class="dax-btn dax-btn-ghost dax-btn-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="docent-scroll min-h-0 flex-1 overflow-y-auto p-3">
            <div x-show="revisionsLoading" class="space-y-3 p-1">
                <div class="dax-skeleton h-16 w-full"></div>
                <div class="dax-skeleton h-16 w-full"></div>
                <div class="dax-skeleton h-16 w-full"></div>
            </div>

            <p x-show="!revisionsLoading && revisions.length === 0" class="p-4 text-center text-sm text-[var(--docent-faint)]">
                No revisions yet.
            </p>

            <template x-for="(rev, index) in revisions" :key="rev.id">
                <div class="group mb-2 rounded-lg border border-[var(--docent-border)] p-3 transition hover:border-[var(--docent-accent)]/40">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-medium text-[var(--docent-fg)]">
                            <span x-text="relativeTime(rev.created_at)"></span>
                            <span x-show="index === 0" class="ml-1 rounded bg-[var(--docent-accent)]/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[var(--docent-accent)]">Latest</span>
                        </span>
                        <button type="button" x-show="index !== 0" @click="restoreRevision(rev.id)"
                                class="dax-btn dax-btn-ghost text-xs opacity-0 transition group-hover:opacity-100">
                            Restore
                        </button>
                    </div>
                    <p class="mt-1.5 line-clamp-2 font-mono text-xs leading-relaxed text-[var(--docent-faint)]" x-text="rev.excerpt"></p>
                </div>
            </template>
        </div>
    </div>
</div>
