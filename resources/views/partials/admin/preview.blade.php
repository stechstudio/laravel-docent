{{-- Preview view: the draft rendered through the real pipeline with the
     admin's own context. Same width and prose layer as the reader — including
     the brand accent, re-asserted here because the admin chrome wears its own. --}}
<div class="mx-auto w-full max-w-[52rem] px-6 pb-24 pt-8 sm:px-10" style="--docent-accent: {{ $docent->accent() }}">
    <div x-show="!previewHtml && !previewLoading" x-cloak class="pt-16 text-center text-sm text-[var(--docent-faint)]">
        Nothing to preview yet.
    </div>

    <template x-if="previewHtml">
        <div>
            <h1 class="text-[2rem] font-bold leading-tight tracking-tight text-slate-900 dark:text-white" x-text="title"></h1>
            <p x-show="fm.description" class="mt-2 text-[15px] text-[var(--docent-faint)]" x-text="fm.description"></p>
            <article class="docent-prose mt-6" x-html="previewHtml"></article>
        </div>
    </template>
</div>
