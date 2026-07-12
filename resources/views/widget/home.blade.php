@extends('docent::widget.layout')

@section('content')
    <div class="px-5 py-6 sm:px-6">
        <div class="mb-6">
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--docent-accent)]">Help center</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">How can we help?</h1>
            <p class="mt-1.5 text-sm leading-6 text-slate-500 dark:text-slate-400">Browse the guides below or search for an answer.</p>
        </div>

        <nav class="docent-widget-nav" aria-label="Documentation">
            <ul class="space-y-5">
                @foreach($navigation as $node)
                    @include('docent::widget.nav-node', ['node' => $node, 'depth' => 0])
                @endforeach
            </ul>
        </nav>
    </div>
@endsection
