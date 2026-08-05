<?php

/** The first (desktop) sidebar, so page body and prev/next links cannot match. */
function docentNavHtml($testCase, string $slug): string
{
    $html = $testCase->get($slug)->assertOk()->getContent();

    preg_match('#<nav class="docent-nav".*?</nav>#s', $html, $nav);

    return $nav[0] ?? '';
}

it('renders a page-backed sub-group header as a link with a separate toggle', function () {
    $nav = docentNavHtml($this, '/docs/guides/setup');

    // guides/deploy has an index.md, so the group label itself links to it and
    // the chevron beside it is a separate, labeled button. The index page no
    // longer appears as a "Deploy Overview" row inside its own group.
    expect($nav)
        ->toMatch('#<a href="http://localhost/docs/guides/deploy"[^>]*>\s*<span class="truncate">Deploy</span>#s')
        ->toContain('aria-label="Toggle Deploy"')
        ->not->toContain('Deploy Overview');
});

it('leaves an index-less sub-group header as a plain toggle', function () {
    $nav = docentNavHtml($this, '/docs/guides/setup');

    // guides/troubleshooting has no index.md: the label still renders, but
    // nothing links to the directory itself.
    expect($nav)
        ->toContain('Troubleshooting')
        ->not->toContain('href="http://localhost/docs/guides/troubleshooting"');
});

it('marks the active landing page and expands the group it heads', function () {
    $nav = docentNavHtml($this, '/docs/guides/deploy');

    // contains() matches the index slug, so the group opens on its own page.
    expect($nav)
        ->toMatch('#<a href="http://localhost/docs/guides/deploy"[^>]*aria-current="page"[^>]*>\s*<span class="truncate">Deploy</span>#s')
        ->toContain('href="http://localhost/docs/guides/deploy/production"');
});

it('preserves sidebar scroll with a store scoped to the site and section', function () {
    $html = $this->get('/docs/guides/setup')->assertOk()->getContent();

    // The default section has no directory, so its segment is empty.
    expect($html)
        ->toContain('sessionStorage')
        ->toContain('docent:docs::nav-scroll');
});

it('scopes the sidebar scroll store to the active section', function () {
    $html = $this->actingAs($this->adminUser())->get('/docs/reports')->assertOk()->getContent();

    expect($html)->toContain('docent:docs:reports:nav-scroll');
});

it('pins a top-level group index as its first sidebar item', function () {
    $nav = docentNavHtml($this, '/docs/guides/setup');

    // Top-level groups keep their always-open <p> header, so the promoted
    // index stays a listed item rather than becoming the header itself.
    expect($nav)->toContain('Guides Overview')
        ->and(strpos($nav, 'href="http://localhost/docs/guides"'))
        ->toBeLessThan(strpos($nav, 'href="http://localhost/docs/guides/setup"'));
});
