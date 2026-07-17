{{--
    The landing-page hero: accent glow, optional eyebrow badge, title,
    description, and an optional prominent search box and/or CTA buttons.
    Custom layouts can use this directly instead of rebuilding the markup.
--}}
@props(['title', 'docent', 'description' => null, 'badge' => null, 'cta' => [], 'search' => false])

<div class="docent-hero">
    <div class="relative mx-auto max-w-3xl px-2 pb-4 pt-10 text-center sm:pt-20">
        @if($badge)
            <span class="docent-hero-badge">{{ $badge }}</span>
        @endif

        <h1 class="text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl dark:text-white">{{ $title }}</h1>

        @if($description)
            <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-500 sm:text-xl dark:text-slate-400">{{ $description }}</p>
        @endif

        @if($search)
            <div class="mx-auto mt-9 max-w-xl">
                <x-docent::search-box size="lg" :docent="$docent" />
            </div>
        @endif

        @if(!empty($cta))
            <div class="{{ $search ? 'mt-6' : 'mt-9' }} flex flex-wrap items-center justify-center gap-3">
                @foreach($cta as $button)
                    <a href="{{ $button['href'] }}" class="{{ ($button['style'] ?? 'primary') === 'secondary' ? 'docent-cta-secondary' : 'docent-cta-primary' }}">{{ $button['label'] }}</a>
                @endforeach
            </div>
        @endif

        {{ $slot }}
    </div>
</div>
