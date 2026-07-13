@if(($navigationLinks ?? []) !== [])
    <nav class="border-b border-slate-200 pb-5 dark:border-slate-800" aria-label="Helpful links">
        <ul class="space-y-1" role="list">
            @foreach($navigationLinks as $link)
                <li>
                    <a href="{{ $link->url }}"
                       @if($link->external) target="_blank" rel="noopener" @endif
                       @if($link->active) aria-current="page" @endif
                       class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm transition {{ $link->active
                           ? 'bg-[color-mix(in_srgb,var(--docent-accent)_12%,transparent)] text-[var(--docent-accent)]'
                           : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
                        @if($linkIcon = $link->iconMarkup())
                            <span class="inline-flex shrink-0 [&_img]:size-4 [&_svg]:size-4" aria-hidden="true">{!! $linkIcon !!}</span>
                        @endif
                        <span class="min-w-0 flex-1 truncate">{{ $link->label }}</span>
                        @if($link->external && ($externalIcon = \STS\Docent\Support\Icon::svg('arrow-top-right-on-square')))
                            <span class="inline-flex shrink-0 text-slate-400 [&_svg]:size-4" aria-hidden="true">{!! $externalIcon !!}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
