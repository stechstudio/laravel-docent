{{--
    Default topbar actions: configured links, the Assistant button, and the
    search button. A layout may replace or suppress this region with
    @section('topbar-actions'); the theme toggle stays in the shell.

    When the page's hero carries its own search box, the topbar search
    starts hidden and fades in once the hero box scrolls out of view
    (data-docent-search-deferred, toggled by an IntersectionObserver).
--}}
@foreach(($topbarLinks ?? []) as $topbarLink)
    <a href="{{ $topbarLink->url }}" @if($topbarLink->external) target="_blank" rel="noopener" @endif
       aria-label="{{ $topbarLink->label }}" title="{{ $topbarLink->label }}"
       class="inline-flex h-9 min-w-9 items-center justify-center rounded-md px-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 max-lg:hidden dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
        @if($topbarIcon = $topbarLink->iconMarkup())
            <span class="inline-flex [&_img]:size-5 [&_svg]:size-5" aria-hidden="true">{!! $topbarIcon !!}</span>
        @else
            <span class="text-sm font-medium">{{ $topbarLink->label }}</span>
        @endif
    </a>
@endforeach
@if($aiEnabled)
    <button type="button" @click="$dispatch('docent:assistant-open')" aria-label="{{ __('docent::ui.assistant.open') }}" title="{{ __('docent::ui.assistant.open_with_shortcut') }}"
            class="relative inline-flex size-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--docent-accent)] dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
        <span class="pointer-fine:hidden absolute left-1/2 top-1/2 size-[max(100%,3rem)] -translate-1/2" aria-hidden="true"></span>
        <span class="shrink-0 [&_svg]:size-4" aria-hidden="true">{!! \STS\Docent\Support\Icon::svg('chat-bubble-left-right') !!}</span>
    </button>
@endif
@if($searchEnabled)
    <button type="button" @click="$dispatch('docent:search-open')" aria-label="{{ __('docent::ui.search.label') }}"
            data-docent-topbar-search @if($heroSearch ?? false) data-docent-search-deferred @endif
            class="group inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-400 transition hover:border-slate-300 hover:text-slate-500 sm:w-64 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <span class="hidden sm:inline">{{ __('docent::ui.search.topbar_placeholder') }}</span>
        <kbd class="ml-auto hidden rounded border border-slate-200 bg-white px-1.5 py-0.5 font-sans text-[11px] font-medium text-slate-400 sm:inline dark:border-slate-700 dark:bg-slate-800" data-docent-kbd>⌘K</kbd>
    </button>
@endif
