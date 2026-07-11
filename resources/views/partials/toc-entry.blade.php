<li>
    <a href="#{{ $entry->slug }}"
       class="block border-l-2 border-transparent py-1 text-slate-500 transition hover:text-slate-900 aria-[current]:border-[var(--docent-accent)] dark:text-slate-400 dark:hover:text-white {{ $entry->level >= 3 ? 'pl-6' : 'pl-3' }} [&.is-active]:border-[var(--docent-accent)] [&.is-active]:font-medium [&.is-active]:text-[var(--docent-accent)]">
        {{ $entry->title }}
    </a>
    @if(!empty($entry->children))
        <ul>
            @foreach($entry->children as $child)
                @include('docent::partials.toc-entry', ['entry' => $child])
            @endforeach
        </ul>
    @endif
</li>
