@php($isGroup = $node instanceof \STS\Docent\Navigation\NavigationGroup)

@if($isGroup)
    <li>
        <p class="mb-1.5 flex items-center gap-2 px-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">
            @if($node->icon && ($groupIcon = \STS\Docent\Support\Icon::svg($node->icon)))
                <span class="inline-flex text-[var(--docent-faint)] [&_svg]:h-4 [&_svg]:w-4" aria-hidden="true">{!! $groupIcon !!}</span>
            @endif
            <span>{{ $node->label }}</span>
        </p>
        <ul class="space-y-1{{ $depth > 0 ? ' ml-3 border-l border-slate-200 pl-3 dark:border-slate-800' : '' }}">
            @foreach($node->items as $item)
                @include('docent::widget.nav-node', ['node' => $item, 'depth' => $depth + 1])
            @endforeach
            @foreach($node->groups as $group)
                @include('docent::widget.nav-node', ['node' => $group, 'depth' => $depth + 1])
            @endforeach
        </ul>
    </li>
@else
    <li>
        <a href="{{ $node->url }}" class="group flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800/70 dark:hover:text-white">
            <span>{{ $node->title }}</span>
            <svg class="shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-[var(--docent-accent)] dark:text-slate-700" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        </a>
    </li>
@endif
