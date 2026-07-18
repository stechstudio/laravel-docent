<?php

it('lists guest-visible pages with full URLs', function () {
    $response = $this->get('/docs/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    expect($response->getContent())
        ->toContain('<loc>http://localhost/docs</loc>')
        ->toContain('<loc>http://localhost/docs/billing/overview</loc>')
        ->toContain('<loc>http://localhost/docs/guides/setup</loc>');
});

it('never widens the sitemap to an authorized viewer', function () {
    $content = $this->actingAs($this->adminUser())
        ->get('/docs/sitemap.xml')
        ->assertOk()
        ->getContent();

    expect($content)
        ->not->toContain('billing/secret')
        ->not->toContain('/reports');
});

it('keeps hidden pages, search-excluded pages, and redirect stubs out of the sitemap', function () {
    $content = $this->get('/docs/sitemap.xml')->assertOk()->getContent();

    expect($content)
        ->not->toContain('guides/advanced')
        ->not->toContain('changelog')
        ->not->toContain('welcome')
        ->not->toContain('hub');

    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/redirect-docs');
    $this->resetDocentScope();

    $redirectContent = $this->get('/docs/sitemap.xml')->assertOk()->getContent();

    expect($redirectContent)
        ->toContain('/docs/guides/setup')
        ->not->toContain('/docs/old-setup')
        ->not->toContain('/docs/older-setup')
        ->not->toContain('/docs/old-secret');
});

it('can disable the sitemap', function () {
    config()->set('docent.seo.sitemap', false);

    $this->get('/docs/sitemap.xml')->assertNotFound();
});

it('renders canonical, social, and structured metadata on pages', function () {
    $html = $this->get('/docs/guides/setup')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="http://localhost/docs/guides/setup">', false)
        ->assertSee('<meta property="og:title" content="Setup — Fixture Docs">', false)
        ->getContent();

    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);
    $structuredData = json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR);

    expect($structuredData)
        ->toHaveKey('@type', 'TechArticle')
        ->toHaveKey('url', 'http://localhost/docs/guides/setup');
});

it('omits social images and uses the summary card by default', function () {
    $html = $this->get('/docs/billing/overview')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="twitter:card" content="summary">')
        ->not->toContain('og:image');
});

it('renders the shared seo.image on pages without their own', function () {
    config()->set('docent.seo.image', 'https://cdn.example.com/help-card.png');
    $this->resetDocentScope();

    $html = $this->get('/docs/billing/overview')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:image" content="https://cdn.example.com/help-card.png">')
        ->toContain('<meta name="twitter:image" content="https://cdn.example.com/help-card.png">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">');
});

it('lets a page front-matter image beat the site default and resolves it absolute', function () {
    config()->set('docent.seo.image', 'https://cdn.example.com/help-card.png');
    $this->resetDocentScope();

    $html = $this->get('/docs/guides/setup')->assertOk()->getContent();
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);
    $structuredData = json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR);

    expect($html)
        ->toContain('<meta property="og:image" content="http://localhost/img/setup-card.png">')
        ->not->toContain('help-card.png');

    expect($structuredData)->toHaveKey('image', 'http://localhost/img/setup-card.png');
});
