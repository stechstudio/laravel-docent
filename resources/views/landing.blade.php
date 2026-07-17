@extends('docent::layout')

@section('content')
    <div class="docent-hero">
        <div class="relative mx-auto max-w-3xl px-2 pb-4 pt-10 text-center sm:pt-20">
            @if($heroBadge ?? null)
                <span class="docent-hero-badge">{{ $heroBadge }}</span>
            @endif

            <h1 class="text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl dark:text-white">{{ $title }}</h1>

            @if($description)
                <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-500 sm:text-xl dark:text-slate-400">{{ $description }}</p>
            @endif

            @if(!empty($heroCta))
                <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                    @foreach($heroCta as $cta)
                        <a href="{{ $cta['href'] }}" class="{{ $cta['style'] === 'secondary' ? 'docent-cta-secondary' : 'docent-cta-primary' }}">{{ $cta['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="docent-prose docent-landing-body mx-auto mt-12 max-w-5xl">
        {!! $html !!}
    </div>
@endsection
