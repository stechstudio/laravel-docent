@php
    $canonical = $docent->fullUrl($currentSlug ?? '');
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'TechArticle',
        'headline' => $seoTitle,
        'description' => $description ?? null,
        'url' => $canonical,
        'inLanguage' => app()->getLocale(),
    ];
@endphp
<link rel="canonical" href="{{ $canonical }}">
<meta property="og:title" content="{{ $seoTitle }}">
@if($description ?? null)<meta property="og:description" content="{{ $description }}">@endif
<meta property="og:type" content="article">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta name="twitter:card" content="summary">
<script type="application/ld+json">@json($structuredData)</script>
