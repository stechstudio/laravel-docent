{{-- Right pane: live preview, debounced through the real render pipeline. The
     prose container reuses the reader's shared visual layer verbatim. --}}
<section class="hidden min-h-0 flex-1 flex-col bg-[var(--docent-panel)]/30 lg:flex" x-show="hasSelection" x-cloak>
    {{-- Header --}}
    <div class="flex flex-none items-center gap-2 border-b border-[var(--docent-border)] px-5 py-3">
        <span class="text-xs font-semibold uppercase tracking-wide text-[var(--docent-faint)]">Preview</span>

        <span x-show="previewIssues.length" x-cloak class="dax-chip dax-chip-draft"
              x-text="previewIssues.length + (previewIssues.length === 1 ? ' issue' : ' issues')"
              :title="previewIssues.map(i => i.message).join('\n')"></span>

        <span x-show="previewLoading" x-cloak class="ml-auto inline-flex items-center gap-1.5 text-xs text-[var(--docent-faint)]">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" class="animate-spin"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg>
            Rendering
        </span>
    </div>

    {{-- Read-only / shadow banners --}}
    <div class="flex-none space-y-2 px-6 pt-4" x-show="readonly || (store === 'database' && shadowed)">
        <template x-if="readonly">
            <div class="dax-banner">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 flex-none"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                <div class="flex-1">
                    <p class="font-medium text-[var(--docent-fg)]">This page lives in the repository.</p>
                    <p class="mt-0.5 text-[var(--docent-faint)]">It’s read-only here. Override it to create an editable database draft that shadows the file.</p>
                    <button type="button" class="dax-btn mt-2 text-xs" @click="override()">Override into database</button>
                </div>
            </div>
        </template>
        <template x-if="store === 'database' && shadowed">
            <div class="dax-banner dax-banner-shadow">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 flex-none"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div class="flex-1">
                    <p class="font-medium text-[var(--docent-fg)]">This draft shadows a repository file.</p>
                    <p class="mt-0.5 text-[var(--docent-faint)]">Readers see this database version. Discard the override to restore the file.</p>
                </div>
            </div>
        </template>
    </div>

    {{-- Rendered document --}}
    <div class="docent-scroll min-h-0 flex-1 overflow-y-auto px-6 py-6">
        <div x-show="!previewHtml && !previewLoading" x-cloak class="pt-10 text-center text-sm text-[var(--docent-faint)]">
            Nothing to preview yet.
        </div>
        <article class="docent-prose mx-auto max-w-2xl" x-html="previewHtml"></article>
    </div>
</section>
