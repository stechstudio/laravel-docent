{{--
    Default topbar navigation: the section switcher tabs. A layout may
    replace or suppress this region with @section('topbar-nav').
--}}
@if(count($sections ?? []) > 1)
    <nav class="docent-scroll min-w-0 max-w-2xl overflow-x-auto max-lg:hidden" aria-label="{{ __('docent::ui.common.documentation_sections') }}">
        <ul class="flex items-center gap-1" role="list">
            @foreach($sections as $section)
                <li class="shrink-0">
                    <a href="{{ $section->url }}" @if($section->active) aria-current="page" @endif
                       class="flex rounded-md px-3 py-2 text-sm transition {{ $section->active
                           ? 'bg-slate-100 text-slate-950 dark:bg-slate-800 dark:text-white'
                           : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
                        {{ $section->label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
