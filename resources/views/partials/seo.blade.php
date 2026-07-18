@php
    $canonical = $docent->fullUrl($currentSlug ?? '');
    $seoImage = $docent->seoImage(($page ?? null)?->image());
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'TechArticle',
        'headline' => $seoTitle,
        'description' => $description ?? null,
        'url' => $canonical,
        'inLanguage' => app()->getLocale(),
    ];

    if ($seoImage !== null) {
        $structuredData['image'] = $seoImage;
    }
@endphp
<link rel="canonical" href="{{ $canonical }}">
<meta property="og:title" content="{{ $seoTitle }}">
@if($description ?? null)<meta property="og:description" content="{{ $description }}">@endif
<meta property="og:type" content="article">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:site_name" content="{{ $siteName }}">
@if($seoImage)
<meta property="og:image" content="{{ $seoImage }}">
<meta name="twitter:image" content="{{ $seoImage }}">
@endif
<meta name="twitter:card" content="{{ $seoImage ? 'summary_large_image' : 'summary' }}">
<script type="application/ld+json">@json($structuredData)</script>
