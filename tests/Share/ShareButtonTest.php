<?php

use STS\Docent\Runtime\DocumentationMode;
use STS\Docent\Sharing\Sharing;
use STS\Docent\Sites\SiteConfig;

it('offers the share button to a viewer who passes the gate', function () {
    $this->actingAs($this->adminUser())
        ->get('/docs/guides/setup')
        ->assertOk()
        ->assertSee('Share this page')
        ->assertSee('?s=', escape: false);
});

it('keeps the share button away from a viewer who does not', function () {
    $this->actingAs($this->memberUser())
        ->get('/docs/guides/setup')
        ->assertOk()
        ->assertDontSee('Share this page');
});

it('mints no links for a viewer who cannot share', function () {
    expect($this->docent()->sharing()->linksFor('guides/setup', $this->memberUser()))->toBeEmpty();
});

it('mints no links for nobody at all', function () {
    expect($this->docent()->sharing()->linksFor('guides/setup', null))->toBeEmpty();
});

it('offers a lifetime for each choice, pre-selecting the configured default', function () {
    $links = $this->docent()->sharing()->linksFor('guides/setup', $this->adminUser());

    expect(array_keys($links))->toBe([7, 30, 90])
        ->and($this->docent()->sharing()->defaultDays())->toBe(30);
});

it('offers no lifetime beyond the configured ceiling', function () {
    // SiteConfig snapshots the config array when the site graph is built, so
    // a lowered ceiling is expressed by building the service against one
    // rather than by mutating config after the fact.
    $sharing = new Sharing(
        new SiteConfig('docs', [
            'share' => ['enabled' => true, 'gate' => 'shareDocentPage', 'ttl' => 7, 'max_ttl' => 14],
        ]),
        app(DocumentationMode::class),
        'docs',
    );

    expect(array_keys($sharing->linksFor('guides/setup', $this->adminUser())))->toBe([7, 14])
        ->and($sharing->defaultDays())->toBe(7);
});

it('clamps a request for longer than the ceiling allows', function () {
    $far = $this->docent()->sharing()->urlFor('guides/setup', days: 3650);
    $capped = $this->docent()->sharing()->urlFor('guides/setup', days: 90);

    expect($far)->toBe($capped);
});
