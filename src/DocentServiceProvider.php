<?php

declare(strict_types=1);

namespace STS\Docent;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use STS\Docent\Admin\Editor;
use STS\Docent\Ai\AiAnswerService;
use STS\Docent\Ai\AiConversationStore;
use STS\Docent\Ai\AiCorpusBuilder;
use STS\Docent\Ai\AiQuestionLogger;
use STS\Docent\Ai\AiRetriever;
use STS\Docent\Ai\PrismGuard;
use STS\Docent\Console\CheckCommand;
use STS\Docent\Console\ClearCommand;
use STS\Docent\Console\GuideCommand;
use STS\Docent\Console\InstallCommand;
use STS\Docent\Console\PruneInsightsCommand;
use STS\Docent\Content\AgentFeed;
use STS\Docent\Content\Models\DocentPage;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Content\Repositories\FilesystemRepository;
use STS\Docent\Documents\Parser\DocumentParser;
use STS\Docent\Documents\Parser\MarkdownDocumentParser;
use STS\Docent\Documents\Renderer\CodeBlockRenderer;
use STS\Docent\Documents\Renderer\ContentHtmlSanitizer;
use STS\Docent\Http\Controllers\Admin\AdminController;
use STS\Docent\Http\Controllers\Admin\ExportController;
use STS\Docent\Http\Controllers\Admin\GroupController;
use STS\Docent\Http\Controllers\Admin\IconController;
use STS\Docent\Http\Controllers\Admin\InsightsController as AdminInsightsController;
use STS\Docent\Http\Controllers\Admin\InsightsExportController;
use STS\Docent\Http\Controllers\Admin\MetaController;
use STS\Docent\Http\Controllers\Admin\PageController as AdminPageController;
use STS\Docent\Http\Controllers\Admin\PageStateController;
use STS\Docent\Http\Controllers\Admin\PreviewController;
use STS\Docent\Http\Controllers\Admin\TreeController;
use STS\Docent\Http\Controllers\Admin\UploadController;
use STS\Docent\Http\Controllers\AskController;
use STS\Docent\Http\Controllers\AskConversationController;
use STS\Docent\Http\Controllers\AskFeedbackController;
use STS\Docent\Http\Controllers\AssetController;
use STS\Docent\Http\Controllers\InsightsController;
use STS\Docent\Http\Controllers\LlmsController;
use STS\Docent\Http\Controllers\PageController;
use STS\Docent\Http\Controllers\SearchController;
use STS\Docent\Http\Controllers\SitemapController;
use STS\Docent\Http\Controllers\UploadsController;
use STS\Docent\Http\Controllers\WidgetController;
use STS\Docent\Http\Controllers\WidgetSuggestionsController;
use STS\Docent\Http\Middleware\SetCurrentSite;
use STS\Docent\Insights\InsightRecorder;
use STS\Docent\Insights\InsightSummary;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Runtime\DocumentationMode;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Search\SearchEngine;
use STS\Docent\Search\SearchIndexer;
use STS\Docent\Sites\CurrentSite;
use STS\Docent\Sites\SiteConfig;
use STS\Docent\Sites\SiteRegistry;
use STS\Docent\Sites\SiteServices;
use STS\Docent\Support\DocentCache;

final class DocentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/docent.php', 'docent');

        $this->app->singleton(IntegrationRegistry::class, static fn (Application $app): IntegrationRegistry => new IntegrationRegistry(static fn (string $class): object => $app->make($class)));

        $this->app->singleton(SiteRegistry::class, static fn (Application $app): SiteRegistry => new SiteRegistry(
            $app,
            $app->make(IntegrationRegistry::class),
        ));
        $this->app->scoped(CurrentSite::class, static fn (Container $app): CurrentSite => new CurrentSite(
            $app->make(SiteRegistry::class),
            $app,
        ));
        $this->app->scoped(SiteServices::class, static fn (Application $app): SiteServices => new SiteServices(
            $app,
            $app->make(SiteRegistry::class),
            $app->make(CurrentSite::class),
        ));

        $this->app->scoped(DocumentationMode::class, static fn (): DocumentationMode => new DocumentationMode);

        $this->app->singleton(DocumentParser::class, MarkdownDocumentParser::class);
        $this->app->singleton(ContentHtmlSanitizer::class);
        $this->app->singleton(PrismGuard::class);

        $this->app->scoped(DocentManager::class, static fn (Application $app): DocentManager => $app->make(SiteRegistry::class)->current());
        $this->app->scoped(Editor::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(Editor::class));
        $this->app->scoped(AgentFeed::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(AgentFeed::class));
        $this->app->scoped(DocumentationRepository::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(DocumentationRepository::class));
        $this->app->scoped(FilesystemRepository::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(FilesystemRepository::class));
        $this->app->scoped(NavigationBuilder::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(NavigationBuilder::class));
        $this->app->scoped(DocentCache::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(DocentCache::class));
        $this->app->scoped(CodeBlockRenderer::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(CodeBlockRenderer::class));
        $this->app->scoped(SearchIndexer::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(SearchIndexer::class));
        $this->app->scoped(SearchEngine::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(SearchEngine::class));
        $this->app->scoped(AiRetriever::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(AiRetriever::class));
        $this->app->scoped(AiCorpusBuilder::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(AiCorpusBuilder::class));
        $this->app->scoped(AiAnswerService::class, static function (Application $app): AiAnswerService {
            $service = $app->make(SiteRegistry::class)->service(AiAnswerService::class);

            if (! $service instanceof AiAnswerService) {
                throw new \LogicException('The Docent site graph did not contain an AI answer service.');
            }

            return $service->ensureConfigured();
        });
        $this->app->scoped(AiQuestionLogger::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(AiQuestionLogger::class));
        $this->app->scoped(AiConversationStore::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(AiConversationStore::class));
        $this->app->scoped(InsightRecorder::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(InsightRecorder::class));
        $this->app->scoped(InsightSummary::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(InsightSummary::class));
    }

    public function boot(): void
    {
        $this->selectMatchedSite();
        $this->registerRoutes();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'docent');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'docent');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'docent');

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/docent.php' => config_path('docent.php')], 'docent-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/docent')], 'docent-views');
            $this->publishes([__DIR__.'/../lang' => lang_path('vendor/docent')], 'docent-lang');
            $this->publishes([__DIR__.'/../resources/dist' => public_path('vendor/docent')], 'docent-assets');
            $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'docent-migrations');

            $this->commands([InstallCommand::class, ClearCommand::class, CheckCommand::class, GuideCommand::class, PruneInsightsCommand::class]);
        }

        $this->registerAboutCommand();
    }

    private function registerRoutes(): void
    {
        foreach ($this->app->make(SiteRegistry::class)->keys() as $key) {
            $site = $this->app->make(SiteRegistry::class)->siteConfig($key);

            Route::group([
                'prefix' => $site->get('route.prefix', 'docs'),
                'domain' => $site->get('route.domain'),
                'middleware' => [...(array) $site->get('route.middleware', ['web']), SetCurrentSite::class.':'.$key],
                'as' => 'docent.'.$key.'.',
            ], function () use ($site): void {
                Route::get('/', [PageController::class, 'home'])->name('home');

                Route::get('/_assets/{file}', AssetController::class)
                    ->where('file', '[A-Za-z0-9._-]+')
                    ->name('asset');

                Route::get('/_uploads/{path}', UploadsController::class)
                    ->where('path', '.*')
                    ->name('upload');

                if ($site->get('search.enabled', true)) {
                    Route::get('/_search', SearchController::class)->name('search');
                }

                if ($site->get('insights.enabled', false)) {
                    Route::post('/_insights', InsightsController::class)->name('insights.store');
                }

                if ($site->get('ai.enabled', false)) {
                    Route::post('/_ask', AskController::class)->name('ask');
                    Route::delete('/_ask/conversation', AskConversationController::class)->name('ask.conversation.destroy');
                    Route::post('/_ask/feedback', AskFeedbackController::class)->name('ask.feedback');
                }

                if ($site->get('admin.enabled', false) && $site->get('database.enabled', false)) {
                    $this->registerAdminRoutes($site);
                }

                if ($site->get('widget.enabled', false)) {
                    Route::get('/_widget', [WidgetController::class, 'home'])->name('widget.home');
                    Route::get('/_widget/_suggestions', WidgetSuggestionsController::class)->name('widget.suggestions');
                    Route::get('/_widget/{slug}', [WidgetController::class, 'show'])
                        ->where('slug', '.*')->name('widget.show');
                }

                Route::get('/llms.txt', [LlmsController::class, 'index'])->name('llms');
                Route::get('/llms-full.txt', [LlmsController::class, 'full'])->name('llms-full');
                Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

                Route::get('/{slug}', [PageController::class, 'show'])->where('slug', '.*')->name('show');
            });
        }
    }

    /**
     * Select the matched route's site before Laravel constructs its
     * controller, so constructor-injected Docent services resolve against the
     * right site. The key is derived from the route name — every Docent route
     * is named `docent.{key}.…` — which works on every supported framework
     * version. The SetCurrentSite route middleware sets the same key again
     * inside the pipeline; it stays as the safety net for requests dispatched
     * without a RouteMatched event (e.g. a host calling a Docent route
     * action manually), so neither selection path should be removed alone.
     */
    private function selectMatchedSite(): void
    {
        $this->app['events']->listen(RouteMatched::class, function (RouteMatched $event): void {
            $name = $event->route->getName();

            if (! is_string($name) || ! str_starts_with($name, 'docent.')) {
                return;
            }

            $key = explode('.', $name)[1] ?? '';

            if ($this->app->make(SiteRegistry::class)->has($key)) {
                $this->app->make(CurrentSite::class)->set($key);
            }
        });
    }

    /**
     * The admin panel and its JSON API, registered inside the docs route group
     * (so prefix/domain/middleware apply) and additionally guarded by the
     * configured gate. Registered before the reader's catch-all `{slug}` route,
     * so the panel path (`admin` by default, `sites.docs.admin.path`) shadows
     * any docs page with that exact slug; page-scoped action routes are likewise
     * declared before the catch-all `{slug}` detail routes so the more
     * specific paths win.
     */
    private function registerAdminRoutes(SiteConfig $site): void
    {
        $path = trim((string) $site->get('admin.path', 'admin'), '/');

        Route::middleware('can:'.$site->get('admin.gate', 'viewDocentAdmin'))->prefix($path)->group(function () use ($site): void {
            Route::get('/', AdminController::class)->name('admin');

            Route::get('api/tree', TreeController::class)->name('admin.tree');
            Route::get('api/meta', MetaController::class)->name('admin.meta');
            Route::get('api/icons', IconController::class)->name('admin.icons');
            Route::post('api/preview', PreviewController::class)->name('admin.preview');
            Route::post('api/uploads', UploadController::class)->name('admin.uploads');

            if ($site->get('insights.enabled', false)) {
                Route::get('insights', AdminInsightsController::class)->name('admin.insights');
                Route::get('insights.csv', InsightsExportController::class)->name('admin.insights.export');
            }

            // Group metadata — declared before the api/pages/{slug} catch-alls so
            // the more specific paths win.
            Route::get('api/groups', [GroupController::class, 'index'])->name('admin.groups.index');
            Route::put('api/groups/{directory}', [GroupController::class, 'update'])
                ->where('directory', '.*')->name('admin.groups.update');
            Route::delete('api/groups/{directory}', [GroupController::class, 'destroy'])
                ->where('directory', '.*')->name('admin.groups.destroy');

            Route::post('api/pages', [AdminPageController::class, 'store'])->name('admin.pages.store');

            Route::get('api/pages/{slug}/revisions', [AdminPageController::class, 'revisions'])
                ->where('slug', '.*')->name('admin.pages.revisions');
            Route::get('api/pages/{slug}/markdown', ExportController::class)
                ->where('slug', '.*')->name('admin.export');
            Route::post('api/pages/{slug}/publish', [PageStateController::class, 'publish'])
                ->where('slug', '.*')->name('admin.pages.publish');
            Route::post('api/pages/{slug}/unpublish', [PageStateController::class, 'unpublish'])
                ->where('slug', '.*')->name('admin.pages.unpublish');
            Route::post('api/pages/{slug}/revert/{revision}', [PageStateController::class, 'revert'])
                ->where('slug', '.*')->where('revision', '[0-9]+')->name('admin.pages.revert');
            Route::post('api/pages/{slug}/override', [PageStateController::class, 'override'])
                ->where('slug', '.*')->name('admin.pages.override');

            Route::get('api/pages/{slug}', [AdminPageController::class, 'show'])
                ->where('slug', '.*')->name('admin.pages.show');
            Route::put('api/pages/{slug}', [AdminPageController::class, 'update'])
                ->where('slug', '.*')->name('admin.pages.update');
            Route::delete('api/pages/{slug}', [AdminPageController::class, 'destroy'])
                ->where('slug', '.*')->name('admin.pages.destroy');
        });
    }

    private function registerAboutCommand(): void
    {
        if (! class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('Docent', function (): array {
            $sites = $this->app->make(SiteRegistry::class);
            $details = ['Version' => DocentManager::VERSION];

            foreach ($sites->keys() as $key) {
                $site = $sites->siteConfig($key);
                $repository = $sites->serviceFor($key, DocumentationRepository::class);

                if (! $repository instanceof DocumentationRepository) {
                    continue;
                }

                $details[$key.' Pages'] = (string) count(iterator_to_array($repository->all()));
                $details[$key.' Route Prefix'] = '/'.(string) $site->get('route.prefix', 'docs');
                $details[$key.' Database'] = $this->databaseSummary($site);
            }

            return $details;
        });
    }

    private function databaseSummary(SiteConfig $site): string
    {
        if (! $site->get('database.enabled', false)) {
            return 'Disabled';
        }

        $configured = $site->get('database.connection');
        $connection = $configured === null ? null : (string) $configured;

        if (! Schema::connection($connection)->hasTable('docent_pages')) {
            return 'Enabled (not migrated)';
        }

        $count = DocentPage::forSite($connection, $site->key)->published()->count();

        return 'Enabled ('.$count.' '.Str::plural('page', $count).')';
    }
}
