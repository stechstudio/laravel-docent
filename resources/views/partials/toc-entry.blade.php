<li class="docent-toc-item docent-toc-level-{{ $entry->level }}">
    <a href="#{{ $entry->slug }}">{{ $entry->title }}</a>
    @if(!empty($entry->children))
        <ul class="docent-toc-list">
            @foreach($entry->children as $child)
                @include('docent::partials.toc-entry', ['entry' => $child])
            @endforeach
        </ul>
    @endif
</li>
