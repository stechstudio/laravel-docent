<?php

use Illuminate\Support\Facades\Artisan;
use STS\Docent\Admin\Editor;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentManager;
use STS\Docent\Documents\Parser\MarkdownDocumentParser;
use STS\Docent\Facades\Docent;
use STS\Docent\Tests\Support\Permission;

// The site graph snapshots its config on first resolution, so the fixture path
// must be in place before anything touches the container.
beforeEach(function () {
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/ability-docs');
});

/**
 * Every `unknown-ability` message from a check run, joined for substring
 * assertions. The fixture holds two real abilities (`settings.manage` in front
 * matter, `reports.export` in a `:::can` block) and one permanent typo.
 */
function unknownAbilities(): string
{
    app()->forgetInstance(DocumentationRepository::class);

    Artisan::call('docent:check', ['--format' => 'json']);

    return implode("\n", array_column(array_filter(
        json_decode(Artisan::output(), true)['issues'],
        fn (array $i): bool => $i['check'] === 'unknown-ability',
    ), 'message'));
}

it('falls back to Gate::has when no surface is declared', function () {
    // Neither ability is Gate::defined, so both read as unknown — the reported
    // behavior for an app that bridges permissions with Gate::before.
    expect(unknownAbilities())
        ->toContain('settings.manage')
        ->toContain('reports.export');
});

it('accepts abilities declared as a config array', function () {
    config()->set('docent.check.abilities', ['settings.manage', 'reports.export']);

    expect(unknownAbilities())
        ->not->toContain('"settings.manage"')
        ->not->toContain('"reports.export"');
});

it('accepts a backed enum class-string as shorthand', function () {
    config()->set('docent.check.abilities', Permission::class);

    expect(unknownAbilities())
        ->not->toContain('"settings.manage"')
        ->not->toContain('"reports.export"');
});

it('accepts a closure registered at runtime', function () {
    app(DocentManager::class)->abilities(fn (): array => array_column(Permission::cases(), 'value'));

    expect(unknownAbilities())
        ->not->toContain('"settings.manage"')
        ->not->toContain('"reports.export"');
});

it('still reports a typo that the declared surface does not contain', function () {
    config()->set('docent.check.abilities', Permission::class);

    expect(unknownAbilities())->toContain('setings.manage');
});

it('replaces Gate::has rather than augmenting it', function () {
    // A declared surface is the whole truth: an ability left out of it reads as
    // unknown even though Gate::has would have accepted it.
    config()->set('docent.check.abilities', ['settings.manage']);

    expect(unknownAbilities())
        ->toContain('"reports.export"')
        ->not->toContain('"settings.manage"');
});

it('accepts a global declaration through the facade', function () {
    Docent::abilities(Permission::class);

    expect(unknownAbilities())
        ->not->toContain('"settings.manage"')
        ->not->toContain('"reports.export"');
});

it('lets a site declaration win over the global one', function () {
    Docent::abilities(['settings.manage', 'reports.export']);
    Docent::site('docs')->abilities(['settings.manage']);

    expect(unknownAbilities())->toContain('"reports.export"');
});

it('lets a runtime registration win over config', function () {
    config()->set('docent.check.abilities', ['settings.manage']);
    app(DocentManager::class)->abilities(fn (): array => ['settings.manage', 'reports.export']);

    expect(unknownAbilities())->not->toContain('"reports.export"');
});

it('resolves the declared surface once per run, not once per ability', function () {
    $calls = 0;
    app(DocentManager::class)->abilities(function () use (&$calls): array {
        $calls++;

        return array_column(Permission::cases(), 'value');
    });

    unknownAbilities();

    expect($calls)->toBe(1);
});

it('rejects a class-string that is not a backed enum', function () {
    config()->set('docent.check.abilities', DocentManager::class);

    unknownAbilities();
})->throws(InvalidArgumentException::class);

it('offers the declared surface to the admin authorize picker', function () {
    config()->set('docent.check.abilities', Permission::class);

    $abilities = app(Editor::class)->pickerMeta()['abilities'];

    expect(array_column($abilities, 'name'))->toBe(['settings.manage', 'reports.export'])
        ->and(array_column($abilities, 'label'))->toContain('Manage settings');
});

it('leaves the admin picker on defined gates when nothing is declared', function () {
    expect(array_column(app(Editor::class)->pickerMeta()['abilities'], 'name'))
        ->toContain('billing.manage');
});

it('feeds the same surface to the admin editor', function () {
    $draft = app(MarkdownDocumentParser::class)->parse(
        "---\ntitle: Draft\nauthorize: settings.manage\n---\n\nBody.\n",
    );

    $messages = fn (): string => implode("\n", array_column(
        app(Editor::class)->draftIssues('draft', $draft),
        'message',
    ));

    expect($messages())->toContain('settings.manage');

    config()->set('docent.check.abilities', Permission::class);
    app()->forgetScopedInstances();

    expect($messages())->not->toContain('settings.manage');
});
