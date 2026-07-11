@if($node instanceof \STS\Docent\Navigation\NavigationGroup)
    <li class="docent-nav-group">
        <span class="docent-nav-group-label">{{ $node->label }}</span>
        <ul class="docent-nav-group-items">
            @foreach($node->items as $item)
                @include('docent::partials.nav-node', ['node' => $item])
            @endforeach
            @foreach($node->groups as $group)
                @include('docent::partials.nav-node', ['node' => $group])
            @endforeach
        </ul>
    </li>
@else
    <li class="docent-nav-item{{ $node->active($currentSlug) ? ' is-active' : '' }}">
        <a href="{{ $node->url }}"@if($node->active($currentSlug)) aria-current="page"@endif>{{ $node->title }}</a>
    </li>
@endif
