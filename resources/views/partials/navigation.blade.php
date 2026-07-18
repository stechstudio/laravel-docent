<nav class="docent-nav" aria-label="{{ __('docent::ui.common.documentation') }}">
    @include('docent::partials.navigation-links')
    <ul class="docent-nav-sections{{ ($navigationLinks ?? []) !== [] ? ' mt-6' : '' }}" role="list">
        @foreach($navigation as $node)
            @include('docent::partials.nav-node', ['node' => $node, 'nested' => false])
        @endforeach
    </ul>
</nav>
