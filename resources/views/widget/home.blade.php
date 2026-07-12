@extends('docent::widget.layout')

@section('content')
    <div class="px-5 py-6 sm:px-6">
        <div class="mb-6">
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--docent-accent)]">Help center</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">How can we help?</h1>
            <p class="mt-1.5 text-sm leading-6 text-slate-500 dark:text-slate-400">Browse the guides below or search for an answer.</p>
        </div>

        <section x-data="docentWidgetSuggestions(document.body.dataset.widgetSuggestionsUrl)"
                 @docent:widget-page.window="load($event.detail.page)"
                 @docent:widget-suggest.window="loadSlugs($event.detail.slugs)"
                 x-show="suggestions.length > 0"
                 x-cloak
                 class="mb-7">
            <div class="mb-2.5 flex items-center justify-between gap-3">
                <h2 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Suggested for this page</h2>
                <span class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></span>
            </div>
            <div class="grid gap-2">
                <template x-for="suggestion in suggestions" :key="suggestion.slug">
                    <a :href="suggestion.url" @click="track(suggestion)"
                       class="group rounded-xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm transition hover:border-[color-mix(in_srgb,var(--docent-accent)_35%,transparent)] hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <span class="flex items-center justify-between gap-3">
                            <span class="text-sm font-semibold text-slate-900 dark:text-white" x-text="suggestion.title"></span>
                            <svg class="shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-[var(--docent-accent)]" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                        </span>
                        <span x-show="suggestion.description" class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400" x-text="suggestion.description"></span>
                    </a>
                </template>
            </div>
        </section>

        <nav class="docent-widget-nav" aria-label="Documentation">
            <ul class="space-y-5">
                @foreach($navigation as $node)
                    @include('docent::widget.nav-node', ['node' => $node, 'depth' => 0])
                @endforeach
            </ul>
        </nav>
    </div>
@endsection
