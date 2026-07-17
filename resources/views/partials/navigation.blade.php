<nav class="docent-nav" aria-label="Documentation">
    @include('docent::partials.navigation-links')
    <ul class="docent-nav-sections{{ ($navigationLinks ?? []) !== [] ? ' mt-6' : '' }}" role="list">
        @foreach($navigation as $node)
            @include('docent::partials.nav-node', ['node' => $node, 'nested' => false])
        @endforeach
    </ul>
</nav>
