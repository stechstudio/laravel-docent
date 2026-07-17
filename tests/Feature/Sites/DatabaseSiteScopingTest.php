<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use STS\Docent\Ai\Models\AiQuestion;
use STS\Docent\Content\Models\DocentPage;
use STS\Docent\Content\Repositories\DatabaseRepository;
use STS\Docent\Http\Controllers\AskFeedbackController;
use STS\Docent\Insights\InsightRecorder;
use STS\Docent\Insights\Models\InsightEvent;
use STS\Docent\Sites\CurrentSite;
use STS\Docent\Sites\SiteRegistry;

function configureDatabaseSites(): void
{
    config()->set('docent.default', 'public');
    config()->set('docent.database.enabled', true);
    config()->set('docent.insights.enabled', true);
    config()->set('docent.sites', [
        'public' => [
            'name' => 'Help Center',
            'route' => ['prefix' => 'help', 'middleware' => ['web']],
            'filesystem' => ['path' => dirname(__DIR__, 2).'/fixtures/docs'],
        ],
        'admin' => [
            'name' => 'Admin Docs',
            'route' => ['prefix' => 'admin/docs', 'middleware' => ['web']],
            'filesystem' => ['path' => dirname(__DIR__, 2).'/fixtures/docs'],
        ],
    ]);
}

beforeEach(function () {
    configureDatabaseSites();
    $this->resetDocentScope();
});

it('allows the same slug on two sites and keeps repositories isolated', function () {
    DocentPage::write('guides/setup', '# Public', site: 'public')->publish();
    DocentPage::write('guides/setup', '# Admin', site: 'admin')->publish();

    expect(DocentPage::query()->count())->toBe(2)
        ->and((new DatabaseRepository(site: 'public'))->find('guides/setup')?->rawContent)->toContain('Public')
        ->and((new DatabaseRepository(site: 'admin'))->find('guides/setup')?->rawContent)->toContain('Admin');
});

it('upserts pages within one site without crossing into another', function () {
    DocentPage::write('page', 'one', site: 'public');
    DocentPage::write('page', 'two', site: 'public');
    DocentPage::write('page', 'admin', site: 'admin');

    expect(DocentPage::query()->where('site', 'public')->count())->toBe(1)
        ->and(DocentPage::query()->where('site', 'admin')->count())->toBe(1);
});

it('reverts a page on its original site and connection', function () {
    $page = DocentPage::write('page', 'v1', site: 'public');
    $first = $page->latestRevision();
    DocentPage::write('page', 'v2', site: 'public');

    $page->fresh()->revertTo($first);

    expect(DocentPage::forSite(null, 'public')->sole()->content)->toBe('v1')
        ->and(DocentPage::forSite(null, 'docs')->count())->toBe(0);
});

it('rejects cross-site AI feedback and insight continuation', function () {
    $question = AiQuestion::forSite(null, 'public')->create([
        'site' => 'public',
        'question' => 'Public question',
        'status' => 'answered',
        'viewer_class' => 'guest',
    ]);
    $sameIdOnAdmin = (new AiQuestion(['site' => 'admin']))->setAttribute('id', $question->getKey());

    expect($sameIdOnAdmin->feedbackToken())->not->toBe($question->feedbackToken());

    $this->app->make(CurrentSite::class)->set('admin');
    $controller = $this->app->make(AskFeedbackController::class);
    $request = Request::create('/admin/docs/_ask/feedback', 'POST', [
        'question_id' => $question->getKey(),
        'feedback_token' => $question->feedbackToken(),
        'thumbs' => 'down',
    ]);

    expect(fn () => $controller($request))->toThrow(ModelNotFoundException::class);

    $sites = $this->app->make(SiteRegistry::class);
    $public = $sites->serviceFor('public', InsightRecorder::class);
    $admin = $sites->serviceFor('admin', InsightRecorder::class);
    $publicId = $public->searchSubmitted('public query', [], 'reader');
    $adminId = $admin->searchSubmitted('admin query', [], 'reader', $publicId);

    expect($adminId)->not->toBe($publicId)
        ->and(InsightEvent::forSite(null, 'public')->sole()->query)->toBe('public query')
        ->and(InsightEvent::forSite(null, 'admin')->sole()->query)->toBe('admin query');
});

it('uses a site-specific non-default database connection', function () {
    config()->set('database.connections.docent_alt', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    $this->artisan('migrate:fresh', [
        '--database' => 'docent_alt',
        '--path' => dirname(__DIR__, 3).'/database/migrations',
        '--realpath' => true,
    ])->assertSuccessful();

    config()->set('docent.sites.public.database.connection', 'docent_alt');
    $this->resetDocentScope();

    DocentPage::write('connected', '# Alternate', site: 'public', connection: 'docent_alt')->publish();
    $repository = $this->app->make(SiteRegistry::class)->serviceFor('public', DatabaseRepository::class);

    expect($repository->find('connected')?->rawContent)->toContain('Alternate')
        ->and(DocentPage::forSite('docent_alt', 'public')->count())->toBe(1)
        ->and(DocentPage::forSite(null, 'public')->count())->toBe(0);
});
