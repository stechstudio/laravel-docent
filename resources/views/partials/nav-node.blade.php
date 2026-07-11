@php($isGroup = $node instanceof \STS\Docent\Navigation\NavigationGroup)

@if($isGroup && ! $nested)
    {{-- Top-level section: label always visible. --}}
    <li class="docent-nav-group">
        <p class="mb-2 flex items-center gap-1.5 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
            @if($node->icon && ($groupIcon = \STS\Docent\Support\Icon::svg($node->icon)))
                <span class="inline-flex text-[var(--docent-faint)] [&_svg]:h-4 [&_svg]:w-4" aria-hidden="true">{!! $groupIcon !!}</span>
            @endif
            <span>{{ $node->label }}</span>
        </p>
        <ul class="space-y-0.5">
            @foreach($node->items as $item)
                @include('docent::partials.nav-node', ['node' => $item, 'nested' => true])
            @endforeach
            @foreach($node->groups as $group)
                @include('docent::partials.nav-node', ['node' => $group, 'nested' => true])
            @endforeach
        </ul>
    </li>
@elseif($isGroup)
    {{-- Nested, collapsible sub-section. --}}
    <li x-data="{ open: {{ $node->contains($currentSlug) ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open" :aria-expanded="open"
                class="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">
            <span class="flex items-center gap-1.5">
                @if($node->icon && ($groupIcon = \STS\Docent\Support\Icon::svg($node->icon)))
                    <span class="inline-flex text-[var(--docent-faint)] [&_svg]:h-3.5 [&_svg]:w-3.5" aria-hidden="true">{!! $groupIcon !!}</span>
                @endif
                <span>{{ $node->label }}</span>
            </span>
            <svg class="transition-transform duration-150" :class="open && 'rotate-90'" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
        <ul x-show="open" x-collapse.duration.150ms class="mt-0.5 space-y-0.5 border-l border-slate-200 pl-2 dark:border-slate-800">
            @foreach($node->items as $item)
                @include('docent::partials.nav-node', ['node' => $item, 'nested' => true])
            @endforeach
            @foreach($node->groups as $group)
                @include('docent::partials.nav-node', ['node' => $group, 'nested' => true])
            @endforeach
        </ul>
    </li>
@else
    @php($active = $node->active($currentSlug))
    <li class="docent-nav-item{{ $active ? ' is-active' : '' }}">
        <a href="{{ $node->url }}" @if($active) aria-current="page" @endif
           class="block rounded-md px-3 py-1.5 text-sm transition {{ $active
               ? 'bg-[color-mix(in_srgb,var(--docent-accent)_12%,transparent)] font-medium text-[var(--docent-accent)]'
               : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
            {{ $node->title }}
        </a>
    </li>
@endif
