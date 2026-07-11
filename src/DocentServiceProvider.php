<?php

declare(strict_types=1);

namespace STS\Docent;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use STS\Docent\Console\CheckCommand;
use STS\Docent\Console\ClearCommand;
use STS\Docent\Console\InstallCommand;
use STS\Docent\Content\Models\DocentPage;
use STS\Docent\Content\Repositories\CompositeRepository;
use STS\Docent\Content\Repositories\DatabaseRepository;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Content\Repositories\FilesystemRepository;
use STS\Docent\Documents\Parser\DocumentParser;
use STS\Docent\Documents\Parser\MarkdownDocumentParser;
use STS\Docent\Documents\Renderer\CodeBlockRenderer;
use STS\Docent\Documents\Renderer\PhikiCodeBlockRenderer;
use STS\Docent\Http\Controllers\AssetController;
use STS\Docent\Http\Controllers\PageController;
use STS\Docent\Http\Controllers\SearchController;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Search\SearchEngine;
use STS\Docent\Search\SearchIndexer;
use STS\Docent\Support\DocentCache;

final class DocentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/docent.php', 'docent');

        $this->app->singleton(IntegrationRegistry::class, static fn (Application $app): IntegrationRegistry => new IntegrationRegistry(static fn (string $class): object => $app->make($class)));

        $this->app->singleton(DocumentParser::class, MarkdownDocumentParser::class);

        $this->app->singleton(CodeBlockRenderer::class, static fn (Application $app): PhikiCodeBlockRenderer => new PhikiCodeBlockRenderer(
            $app->make(DocentCache::class),
        ));

        $this->app->singleton(DocumentationRepository::class, static function (Application $app): DocumentationRepository {
            $filesystem = new FilesystemRepository(
                $app['config']->get('docent.filesystem.path') ?? $app->resourcePath('docs'),
            );

            if (! $app['config']->get('docent.database.enabled', false)) {
                return $filesystem;
            }

            // Database over filesystem: a DB page overrides a file of the same slug.
            return new CompositeRepository(
                new DatabaseRepository($app['config']->get('docent.database.connection')),
                $filesystem,
            );
        });

        $this->app->singleton(DocentCache::class, static fn (Application $app): DocentCache => new DocentCache(
            $app['cache']->store($app['config']->get('docent.cache.store')),
            $app['config']->get('docent.cache.prefix', 'docent'),
        ));

        $this->app->singleton(NavigationBuilder::class, static fn (Application $app): NavigationBuilder => new NavigationBuilder(
            $app->make(DocumentationRepository::class),
            $app->make(IntegrationRegistry::class),
            $app->make(DocentCache::class),
            static fn (string $slug): string => $slug === '' ? route('docent.home') : route('docent.show', $slug),
        ));

        $this->app->singleton(DocentManager::class, static fn (Application $app): DocentManager => new DocentManager(
            $app->make(IntegrationRegistry::class),
            $app->make(DocumentationRepository::class),
            $app->make(DocumentParser::class),
            $app->make(DocentCache::class),
            $app->make(NavigationBuilder::class),
            $app->make(CodeBlockRenderer::class),
        ));

        $this->app->singleton(SearchIndexer::class, static fn (Application $app): SearchIndexer => new SearchIndexer(
            $app->make(DocumentationRepository::class),
            $app->make(DocentCache::class),
            $app->make(DocentManager::class),
        ));

        $this->app->singleton(SearchEngine::class, static fn (Application $app): SearchEngine => new SearchEngine(
            $app->make(SearchIndexer::class),
            $app->make(DocentManager::class),
        ));
    }

    public function boot(): void
    {
        $this->registerRoutes();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'docent');

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/docent.php' => config_path('docent.php')], 'docent-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/docent')], 'docent-views');
            $this->publishes([__DIR__.'/../resources/dist' => public_path('vendor/docent')], 'docent-assets');
            $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'docent-migrations');

            $this->commands([InstallCommand::class, ClearCommand::class, CheckCommand::class]);
        }

        $this->registerAboutCommand();
    }

    private function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('docent.route.prefix', 'docs'),
            'domain' => config('docent.route.domain'),
            'middleware' => config('docent.route.middleware', ['web']),
        ], function (): void {
            Route::get('/', [PageController::class, 'home'])->name('docent.home');

            Route::get('/_assets/{file}', AssetController::class)
                ->where('file', '[A-Za-z0-9._-]+')
                ->name('docent.asset');

            if (config('docent.search.enabled', true)) {
                Route::get('/_search', SearchController::class)->name('docent.search');
            }

            Route::get('/{slug}', [PageController::class, 'show'])->where('slug', '.*')->name('docent.show');
        });
    }

    private function registerAboutCommand(): void
    {
        if (! class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('Docent', fn (): array => [
            'Version' => DocentManager::VERSION,
            'Pages' => (string) count(iterator_to_array($this->app->make(DocumentationRepository::class)->all())),
            'Route Prefix' => '/'.config('docent.route.prefix', 'docs'),
            'Database' => $this->databaseSummary(),
        ]);
    }

    private function databaseSummary(): string
    {
        if (! config('docent.database.enabled', false)) {
            return 'Disabled';
        }

        $connection = config('docent.database.connection');

        if (! Schema::connection($connection)->hasTable('docent_pages')) {
            return 'Enabled (not migrated)';
        }

        $count = DocentPage::on($connection)->published()->count();

        return 'Enabled ('.$count.' '.Str::plural('page', $count).')';
    }
}
