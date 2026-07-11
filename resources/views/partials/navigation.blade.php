<ul class="docent-nav">
    @foreach($navigation as $node)
        @include('docent::partials.nav-node', ['node' => $node])
    @endforeach
</ul>
