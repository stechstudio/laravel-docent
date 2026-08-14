<?php

use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;

it('shows a can block to the admin who would mint the link', function () {
    $this->actingAs($this->adminUser())
        ->get('/docs/guides/setup')
        ->assertSee('You can manage billing.');
});

it('holds back a can block the sharer could see themselves', function () {
    $this->get($this->shareUrl('guides/setup'))
        ->assertOk()
        ->assertDontSee('You can manage billing.');
});

it('renders a dynamic value as its label instead of resolving it', function () {
    $this->get($this->shareUrl('guides/setup'))
        ->assertOk()
        ->assertSee('{Account plan}')
        ->assertDontSee('Team Plan');
});

it('never calls a host value resolver', function () {
    $calls = 0;

    $this->app->make(IntegrationRegistry::class)
        ->value('account.plan', function () use (&$calls): string {
            $calls++;

            return 'Team Plan';
        }, 'Account plan');

    $this->get($this->shareUrl('guides/setup'))->assertOk();

    expect($calls)->toBe(0);
});

it('never calls a host link resolver', function () {
    $calls = 0;

    $this->app->make(IntegrationRegistry::class)
        ->link('billing.settings', function () use (&$calls): string {
            $calls++;

            return '/billing/settings';
        }, 'Billing settings');

    // Authored as a Markdown link, so the label renders unlinked rather than
    // as a brace placeholder — the reader loses a destination they could not
    // have followed anyway, not the sentence around it.
    $this->get($this->shareUrl('guides/setup'))
        ->assertOk()
        ->assertSee('Manage it in the billing settings.')
        ->assertDontSee('/billing/settings');

    expect($calls)->toBe(0);
});

it('never renders a host component', function () {
    $this->get($this->shareUrl('guides/setup'))
        ->assertOk()
        // The plan-usage component renders this for a signed-in reader.
        ->assertDontSee('Plan usage');
});

it('survives a resolver that would fatal on an anonymous reader', function () {
    $this->app->make(IntegrationRegistry::class)
        ->value('account.plan', fn (DocumentationContext $context): string => $context->user->name, 'Account plan');

    $this->get($this->shareUrl('guides/setup'))->assertOk()->assertSee('{Account plan}');
});

it('shows a condition block to the admin who would mint the link', function () {
    config()->set('docent_test.beta', true);

    $this->actingAs($this->adminUser())
        ->get('/docs/guides/setup')
        ->assertSee('Beta features are enabled.');
});

it('holds back a condition block the sharer could see', function () {
    config()->set('docent_test.beta', true);

    $this->get($this->shareUrl('guides/setup'))
        ->assertOk()
        ->assertDontSee('Beta features are enabled.');
});

it('never calls a host condition predicate', function () {
    $calls = 0;

    $this->app->make(IntegrationRegistry::class)
        ->condition('beta-features', function () use (&$calls): bool {
            $calls++;

            return true;
        });

    $this->get($this->shareUrl('guides/setup'))->assertOk();

    expect($calls)->toBe(0);
});

it('never calls a host audience predicate', function () {
    $calls = 0;

    $this->app->make(IntegrationRegistry::class)
        ->audience('internal', function () use (&$calls): bool {
            $calls++;

            return true;
        });

    $this->get($this->shareUrl('guides/setup'))->assertOk();

    expect($calls)->toBe(0);
});

it('will not share a page the guest context cannot authorize', function () {
    // billing/secret carries an authorize: key, so a guest fails it however
    // the link was minted.
    $this->get($this->shareUrl('billing/secret'))->assertNotFound();
});
