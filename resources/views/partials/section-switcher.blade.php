@if(count($sections ?? []) > 1)
    <nav class="border-b border-slate-200 pb-5 dark:border-slate-800" aria-label="{{ __('docent::ui.common.documentation_sections') }}">
        <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('docent::ui.common.sections') }}</p>
        <ul class="mt-2 space-y-1" role="list">
            @foreach($sections as $section)
                <li>
                    <a href="{{ $section->url }}" @if($section->active) aria-current="page" @endif
                       class="flex rounded-md px-3 py-2 text-sm transition {{ $section->active
                           ? 'bg-[color-mix(in_srgb,var(--docent-accent)_12%,transparent)] text-[var(--docent-accent)]'
                           : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
                        {{ $section->label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
