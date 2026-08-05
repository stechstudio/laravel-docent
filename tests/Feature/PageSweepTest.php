<?php

use PHPUnit\Framework\AssertionFailedError;
use STS\Docent\DocentManager;
use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Testing\InteractsWithDocs;

uses(InteractsWithDocs::class);

/**
 * Point the site at the sweep fixture and make `explodes` a component that
 * throws, standing in for anything that can fail at render time.
 */
function sweepFixture(bool $explodes = true): void
{
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/sweep-docs');

    app(DocentManager::class)->component('explodes', $explodes
        ? function (): never {
            throw new RuntimeException('This component always blows up.');
        }
        : new class implements DocumentationComponent
        {
            public function render(DocumentationContext $context, array $attributes): string
            {
                return '<p>Fine.</p>';
            }
        });
}

it('enumerates page slugs using Docent own derivation', function () {
    // Against the standard fixture tree, so the index conventions are covered:
    // index.md is the empty slug and foo/index.md collapses to foo.
    $slugs = $this->docs()->pages();

    expect($slugs)
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

it('returns the slugs sorted so a sweep is deterministic', function () {
    sweepFixture();

    expect($this->docs()->pages())->toBe(['', 'gated', 'open']);
});

it('sweeps every page a viewer can see', function () {
    sweepFixture(explodes: false);

    $this->docs()->as($this->adminUser())->assertAllPagesRender();
});

it('skips pages the viewer may not see', function () {
    sweepFixture();

    // `gated` throws, but a member cannot see it, so the sweep must not touch it.
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

it('sweeps as a guest when no viewer is given', function () {
    sweepFixture();

    // A guest fails the billing.manage gate, so `gated` is out of scope.
    $this->docs()->assertAllPagesRender();
});

it('honors an audience alongside the viewer', function () {
    sweepFixture(explodes: false);

    $this->docs()->as($this->adminUser())->forAudience('internal')->assertAllPagesRender();
});
