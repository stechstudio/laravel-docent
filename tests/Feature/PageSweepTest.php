<?php

use PHPUnit\Framework\AssertionFailedError;
use STS\Docent\DocentManager;
use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Testing\InteractsWithDocs;

uses(InteractsWithDocs::class);

/**
 * Point the site at the sweep fixture. `explodes` stands in for anything that
 * can fail at render time; when it doesn't explode it records which pages were
 * actually rendered, so a test can prove coverage rather than just absence of
 * failure.
 *
 * @param  array<int, string>  $rendered
 */
function sweepFixture(bool $explodes = true, ?array &$rendered = null): void
{
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/sweep-docs');
    $rendered ??= [];

    app(DocentManager::class)->component('explodes', $explodes
        ? function (): never {
            throw new RuntimeException('This component always blows up.');
        }
        : new class($rendered) implements DocumentationComponent
        {
            /** @param array<int, string> $rendered */
            public function __construct(private array &$rendered) {}

            public function render(DocumentationContext $context, array $attributes): string
            {
                $this->rendered[] = $attributes['id'] ?? 'unknown';

                return '<p>Fine.</p>';
            }
        });
}

it('enumerates page slugs using Docent own derivation', function () {
    // Against the standard fixture tree, so the index conventions are covered:
    // index.md is the empty slug and foo/index.md collapses to foo.
    expect($this->docs()->pages())
        ->toContain('')
        ->toContain('guides/setup')
        ->toContain('guides/deploy')
        ->not->toContain('index')
        ->not->toContain('guides/deploy/index');
});

it('leaves partials out of the page list', function () {
    expect($this->docs()->pages())
        ->not->toContain('_partials/loop')
        ->not->toContain('_partials/permissions-note');
});

it('leaves redirect stubs out of the page list', function () {
    // A stub is an alias that never renders; counting it would make
    // "every page renders" mean something other than it says.
    sweepFixture();

    expect($this->docs()->pages())->toBe(['', 'audience', 'gated', 'open']);
});

it('sweeps every page a viewer can see', function () {
    $rendered = [];
    sweepFixture(explodes: false, rendered: $rendered);

    $this->docs()->as($this->adminUser())->forAudience('internal')->assertAllPagesRender();

    // Proves the sweep really rendered both gated pages rather than skipping
    // everything and passing.
    expect($rendered)->toHaveCount(2);
});

it('skips pages the viewer may not see', function () {
    sweepFixture();

    // `gated` and `audience` both throw, but a member can see neither, so the
    // sweep must not touch them — `index` and `open` still give it coverage.
    $this->docs()->as($this->memberUser())->assertAllPagesRender();
});

it('fails and names the page when one cannot render', function () {
    sweepFixture();

    $this->docs()->as($this->adminUser())->assertAllPagesRender();
})->throws(AssertionFailedError::class, 'gated');

it('surfaces the underlying error in the failure message', function () {
    sweepFixture();

    $this->docs()->as($this->adminUser())->assertAllPagesRender();
})->throws(AssertionFailedError::class, 'This component always blows up.');

it('keeps going after a failure so a broken corpus reports at once', function () {
    sweepFixture();
    config()->set('docent_test.internal', true);

    try {
        $this->docs()->as($this->adminUser())->forAudience('internal')->assertAllPagesRender();
        $this->fail('Expected the sweep to fail.');
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toContain('2 of 4 failed')
            ->toContain('gated')
            ->toContain('audience');
    }
});

it('fails rather than passing when the viewer can see nothing', function () {
    // The trap this guards: a misconfigured gate denying every page would
    // otherwise render zero pages and report success.
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/all-gated-docs');

    $this->docs()->as($this->memberUser())->assertAllPagesRender();
})->throws(AssertionFailedError::class, 'render at least one page');

it('sweeps as a guest when no viewer is given', function () {
    sweepFixture();

    // A guest fails both gates, so only the ungated pages are in scope.
    $this->docs()->assertAllPagesRender();
});

it('gives page assertions the viewer set on the tester', function () {
    // Otherwise `->as($admin)->page(...)` silently evaluates as a guest, which
    // is exactly the trap `search()` already avoids.
    $this->docs()->as($this->adminUser())->page('billing/secret')->assertVisible();
    $this->docs()->as($this->memberUser())->page('billing/secret')->assertNotVisible();
});

it('lets as(null) clear a viewer back to guest', function () {
    $docs = $this->docs()->as($this->adminUser());

    $docs->as(null)->page('billing/secret')->assertNotVisible();
});
