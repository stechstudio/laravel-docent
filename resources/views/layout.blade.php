<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($title ?? null) ? $title.' — '.$siteName : $siteName }}</title>
    @isset($description)
        @if($description)<meta name="description" content="{{ $description }}">@endif
    @endisset
</head>
<body class="docent">
    <header class="docent-header">
        <a href="{{ $homeUrl }}" class="docent-brand">{{ $siteName }}</a>
    </header>

    <div class="docent-shell">
        <aside class="docent-sidebar">
            @include('docent::partials.navigation')
        </aside>

        <main class="docent-content">
            @yield('content')
        </main>

        <nav class="docent-toc" aria-label="On this page">
            @include('docent::partials.toc')
        </nav>
    </div>
</body>
</html>
