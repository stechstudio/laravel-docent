{{--
    A single shared page, read by someone who is not signed in.

    Deliberately not extending docent::layout: there is no navigation, no
    search, no assistant, and no session behind this request, so the shell's
    CSRF token, Alpine stores, and sidebar have nothing to attach to. The
    stylesheet and script are still the package's own, so content components
    (tabs, steps, code copy, image lightbox) behave the way they do anywhere
    else.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="docent-scroll">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @php($seoTitle = $title && ! str_contains($siteName, $title) ? $title.' — '.$siteName : $siteName)
    <title>{{ $seoTitle }}</title>
    @if($description)<meta name="description" content="{{ $description }}">@endif

    @if($docent->favicon())
        <link rel="icon" href="{{ $docent->favicon() }}">
    @endif
    @if($docent->fontHref())
        <link rel="preconnect" href="{{ $docent->fontHref() }}" crossorigin>
        <link rel="stylesheet" href="{{ $docent->fontHref() }}">
    @endif

    <script>(function(){try{var t=localStorage.getItem('docentTheme');var d=t?t==='dark':window.matchMedia('(prefers-color-scheme: dark)').matches;document.documentElement.classList.toggle('dark',d);}catch(e){}})();</script>
    @include('docent::partials.ui-strings')

    <link rel="stylesheet" href="{{ $docent->asset('docent.css') }}">
    <script defer src="{{ $docent->asset('docent.js') }}"></script>

    <style>{!! $docent->themeStyles() !!}</style>
</head>
<body class="min-h-screen bg-[var(--docent-bg)] text-[var(--docent-fg)] antialiased">
    <div class="mx-auto flex min-h-screen max-w-[48rem] flex-col px-5 py-10 sm:px-8 sm:py-16">
        <header class="mb-10 flex items-center gap-2">
            @if($docent->logo())
                <img src="{{ $docent->logo() }}" alt="{{ $siteName }}" class="h-7 w-auto{{ $docent->logoDark() ? ' dark:hidden' : '' }}">
                @if($docent->logoDark())
                    <img src="{{ $docent->logoDark() }}" alt="{{ $siteName }}" class="hidden h-7 w-auto dark:block">
                @endif
            @else
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-[var(--docent-accent)] text-sm font-bold text-white">{{ mb_substr($siteName, 0, 1) }}</span>
                <span class="text-[15px] font-semibold tracking-tight text-slate-900 dark:text-white">{{ $siteName }}</span>
            @endif
        </header>

        <main id="docent-content" class="docent-main flex-1">
            <article>
                <h1 class="text-[2.25rem] font-bold leading-tight tracking-tight text-slate-900 dark:text-white">{{ $title }}</h1>

                @if($description)
                    <p class="mt-3 text-lg text-slate-500 dark:text-slate-400">{{ $description }}</p>
                @endif

                <div class="docent-prose mt-8">
                    {!! $html !!}
                </div>
            </article>
        </main>

        <footer class="mt-16 border-t border-slate-200 pt-6 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
            <p>
                {{ __('docent::ui.share.shared_notice') }}
                @if($loginUrl)
                    <a href="{{ $loginUrl }}" class="font-medium text-[var(--docent-accent)] hover:underline">{{ __('docent::ui.share.sign_in') }}</a>.
                @endif
            </p>
        </footer>
    </div>
</body>
</html>
