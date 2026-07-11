<?php

use STS\Docent\Testing\InteractsWithDocs;

uses(InteractsWithDocs::class);

it('asserts page visibility per viewer', function () {
    $this->docs()->page('billing/secret')->as($this->adminUser())->assertVisible();
    $this->docs()->page('billing/secret')->as($this->memberUser())->assertNotVisible();
});

it('renders authorized content the viewer may see', function () {
    $this->docs()->page('guides/setup')->as($this->adminUser())
        ->assertVisible()
        ->assertSee('Install the thing')
        ->assertSee('You can manage billing.')          // :::can billing.manage
        ->assertSee('Team Plan')                        // {{ value:account.plan }}
        ->assertSee('You need the correct permissions')  // resolved :::include
        ->assertSee('Usage for the pro plan');           // <docs-component name="plan-usage" plan="pro" />
});

it('hides content the viewer is not authorized to see', function () {
    $this->docs()->page('guides/setup')->as($this->memberUser())
        ->assertVisible()
        ->assertSee('Install the thing')
        ->assertDontSee('You can manage billing.');
});

it('hides inactive condition blocks by default', function () {
    $this->docs()->page('guides/setup')->as($this->adminUser())
        ->assertDontSee('Beta features are enabled.');
});

it('reveals condition blocks when the condition passes', function () {
    config()->set('docent_test.beta', true);

    $this->docs()->page('guides/setup')->as($this->adminUser())
        ->assertSee('Beta features are enabled.');
});

it('runs the real search engine and filters by authorization', function () {
    $this->docs()->search('billing', as: $this->adminUser())->assertSees('Secret Billing');
    $this->docs()->search('billing', as: $this->memberUser())
        ->assertSees('Billing Overview')
        ->assertMissing('Secret Billing');
});

it('omits search-excluded pages from results', function () {
    $this->docs()->search('flibbertigibbet', as: $this->adminUser())->assertMissing('Changelog');
});
