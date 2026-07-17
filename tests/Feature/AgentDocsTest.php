<?php

it('serves contextual markdown by suffix without leaking gated content', function () {
    $this->get('/docs/billing/secret.md')->assertNotFound();

    $guest = $this->get('/docs/guides/setup.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Vary', 'Accept');

    expect($guest->getContent())
        ->toStartWith("# Setup\n\n> Set things up.")
        ->toContain('You need the correct permissions')
        ->toContain('{Account plan}')
        ->toContain('http://localhost/billing/settings')
        ->not->toContain('Team Plan')
        ->not->toContain('You can manage billing');

    $this->actingAs($this->adminUser());

    $this->get('/docs/billing/secret.md')
        ->assertOk()
        ->assertSee('Only billing admins can read this.', false);

    $admin = $this->get('/docs/guides/setup.md')->assertOk()->getContent();
    expect($admin)->toContain('You can manage billing');
});

it('serves hidden and search-excluded pages individually', function () {
    $this->get('/docs/guides/advanced.md')
        ->assertOk()
        ->assertSee('This page is hidden from navigation.', false);

    $this->get('/docs/changelog.md')
        ->assertOk()
        ->assertSee('flibbertigibbet', false);
});

it('negotiates markdown only when it is explicitly preferred', function () {
    $this->withHeader('Accept', '*/*')
        ->get('/docs/guides/setup')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

    $this->withHeader('Accept', 'text/html,application/xhtml+xml,*/*;q=0.8')
        ->get('/docs/guides/setup')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('<html', false);

    $this->withHeader('Accept', 'text/markdown')
        ->get('/docs/guides/setup')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertSee('# Setup', false);

    $this->get('/docs/index.md')
        ->assertOk()
        ->assertSee('[Setup](http://localhost/docs/guides/setup.md)', false);
});

it('publishes a permission-filtered llms index in navigation order', function () {
    config()->set('docent.sites.docs.description', 'Help for the fixture application.');

    $guest = $this->get('/docs/llms.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->getContent();

    expect($guest)
        ->toStartWith("# Fixture Docs\n\n> Help for the fixture application.")
        ->toContain('## Documentation')
        ->toContain('## Guides')
        ->toContain('[Setup](http://localhost/docs/guides/setup.md): Set things up.')
        ->not->toContain('Advanced')
        ->not->toContain('Secret Billing')
        ->not->toContain('## Reports');

    $this->actingAs($this->adminUser());
    $admin = $this->get('/docs/llms.txt')->assertOk()->getContent();

    expect($admin)
        ->toContain('Secret Billing')
        ->toContain('## Reports')
        ->and(strpos($admin, '## Documentation'))->toBeLessThan(strpos($admin, '## Reports'));
});

it('publishes a leak-safe full corpus and excludes search-excluded pages', function () {
    $guest = $this->get('/docs/llms-full.txt')->assertOk()->getContent();

    expect($guest)
        ->toContain('# Setup')
        ->toContain('{Account plan}')
        ->not->toContain('Team Plan')
        ->not->toContain('You can manage billing')
        ->not->toContain('Only billing admins')
        ->not->toContain('flibbertigibbet');

    $this->actingAs($this->adminUser());
    $admin = $this->get('/docs/llms-full.txt')->assertOk()->getContent();

    expect($admin)
        ->toContain('You can manage billing')
        ->toContain('Only billing admins')
        ->not->toContain('flibbertigibbet')
        ->and(strpos($admin, '# Secret Billing'))->toBeLessThan(strpos($admin, '# Reports'));
});

it('advertises both llms files on html pages', function () {
    $this->get('/docs/guides/setup')
        ->assertOk()
        ->assertHeader('Vary', 'Accept')
        ->assertHeader('Link', '</docs/llms.txt>; rel="llms-txt", </docs/llms-full.txt>; rel="llms-full-txt"');
});
