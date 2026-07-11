<?php

/**
 * Theming tokens flow entirely through the dynamic <style> block and a few head
 * links — no CSS rebuild, no published views. These assert the config → markup
 * pipeline: accent, gray palette, radius, fonts, favicon, and the logo chain.
 */
function docsBody(): string
{
    return test()->get('/docs')->assertOk()->getContent();
}

it('emits the accent and defaults with no palette, radius, or font overrides', function () {
    $html = docsBody();

    expect($html)->toContain('--docent-accent:#6366f1;')
        // Slate is the identity palette and Default the identity radius, so
        // neither remaps its variables in the inline block.
        ->and($html)->not->toContain('--color-slate-200:')
        ->and($html)->not->toContain('--radius-lg:')
        ->and($html)->not->toContain('--font-sans:')
        ->and($html)->not->toContain('--font-mono:');
});

it('emits a configured accent', function () {
    config()->set('docent.theme.accent', '#e11d48');

    expect(docsBody())->toContain('--docent-accent:#e11d48;');
});

it('remaps the slate variables when a non-default gray palette is chosen', function () {
    config()->set('docent.theme.gray', 'zinc');

    // Zinc's 200 shade, proving the slate utility variables are remapped.
    expect(docsBody())->toContain('--color-slate-200:oklch(92% 0.004 286.32);');
});

it('leaves the palette untouched for slate', function () {
    config()->set('docent.theme.gray', 'slate');

    expect(docsBody())->not->toContain('--color-slate-200:');
});

it('scales radius down for sharp and up for soft', function () {
    config()->set('docent.theme.radius', 'sharp');
    expect(docsBody())->toContain('--radius-lg:0.25rem;');

    config()->set('docent.theme.radius', 'soft');
    expect(docsBody())->toContain('--radius-lg:0.75rem;');
});

it('emits configured font stacks', function () {
    config()->set('docent.theme.font.sans', "'Inter', system-ui, sans-serif");
    config()->set('docent.theme.font.mono', "'Fira Code', monospace");

    expect(docsBody())
        ->toContain("--font-sans:'Inter', system-ui, sans-serif;")
        ->toContain("--font-mono:'Fira Code', monospace;");
});

it('emits a webfont stylesheet link only when font.href is set', function () {
    expect(docsBody())
        ->not->toContain('rel="preconnect"')
        ->and(docsBody())->not->toContain('fonts.bunny.net');

    config()->set('docent.theme.font.href', 'https://fonts.bunny.net/css?family=inter');

    $html = docsBody();

    expect($html)
        ->toContain('<link rel="preconnect" href="https://fonts.bunny.net/css?family=inter" crossorigin>')
        ->toContain('<link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter">');
});

it('emits a favicon link only when configured', function () {
    expect(docsBody())->not->toContain('rel="icon"');

    config()->set('docent.theme.favicon', '/favicon.svg');

    expect(docsBody())->toContain('<link rel="icon" href="/favicon.svg">');
});

it('falls back to a wordmark when no logo is configured', function () {
    $html = docsBody();

    expect($html)->toContain('Fixture Docs')
        ->and($html)->not->toContain('dark:hidden');
});

it('renders a single logo with no dark swap when only logo is set', function () {
    config()->set('docent.theme.logo', '/img/logo.svg');

    $html = docsBody();

    expect($html)->toContain('src="/img/logo.svg"')
        // No dark-mode logo, so the light logo is not conditionally hidden.
        ->and($html)->not->toContain('dark:hidden');
});

it('renders both logos with a css swap when logo_dark is set', function () {
    config()->set('docent.theme.logo', '/img/logo.svg');
    config()->set('docent.theme.logo_dark', '/img/logo-dark.svg');

    $html = docsBody();

    expect($html)
        ->toContain('src="/img/logo.svg" alt="Fixture Docs" class="h-7 w-auto dark:hidden"')
        ->toContain('src="/img/logo-dark.svg" alt="Fixture Docs" class="hidden h-7 w-auto dark:block"');
});

it('renders the logomark for the compact header when set', function () {
    config()->set('docent.theme.logomark', '/img/mark.svg');

    expect(docsBody())
        ->toContain('src="/img/mark.svg" alt="Fixture Docs" class="h-7 w-7 sm:hidden"');
});
