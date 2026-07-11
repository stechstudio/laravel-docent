<nav class="docent-nav" aria-label="Documentation">
    <ul class="space-y-7">
        @foreach($navigation as $node)
            @include('docent::partials.nav-node', ['node' => $node, 'nested' => false])
        @endforeach
    </ul>
</nav>
