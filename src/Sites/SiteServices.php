<?php

declare(strict_types=1);

namespace STS\Docent\Sites;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use RuntimeException;
use STS\Docent\Ai\AiAnswerService;
use STS\Docent\Ai\AiConversationStore;
use STS\Docent\Ai\AiCorpusBuilder;
use STS\Docent\Ai\AiQuestionLogger;
use STS\Docent\Ai\AiRetriever;
use STS\Docent\Ai\PrismGuard;
use STS\Docent\Content\Repositories\CompositeRepository;
use STS\Docent\Content\Repositories\DatabaseRepository;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Content\Repositories\FilesystemRepository;
use STS\Docent\DocentManager;
use STS\Docent\Documents\Parser\DocumentParser;
use STS\Docent\Documents\Renderer\CodeBlockRenderer;
use STS\Docent\Documents\Renderer\ContentHtmlSanitizer;
use STS\Docent\Documents\Renderer\PhikiCodeBlockRenderer;
use STS\Docent\Insights\InsightRecorder;
use STS\Docent\Insights\InsightSummary;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Runtime\DocumentationMode;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Search\SearchEngine;
use STS\Docent\Search\SearchIndexer;
use STS\Docent\Search\SearchQueryAnalyzer;
use STS\Docent\Support\DocentCache;

/** Builds one internally consistent service graph per site and scope. */
final class SiteServices
{
    /** @var array<string, array<class-string, object>> */
    private array $services = [];

    public function __construct(
        private readonly Application $app,
        private readonly SiteRegistry $sites,
        private readonly CurrentSite $currentSite,
    ) {}

    public function current(): DocentManager
    {
        return $this->site($this->currentSite->key());
    }

    public function site(?string $key = null): DocentManager
    {
        $service = $this->serviceFor($key ?? $this->sites->defaultKey(), DocentManager::class);

        if (! $service instanceof DocentManager) {
            throw new RuntimeException('The Docent site graph did not contain a manager.');
        }

        return $service;
    }

    public function service(string $class): object
    {
        return $this->serviceFor($this->currentSite->key(), $class);
    }

    public function serviceFor(string $key, string $class): object
    {
        if (! $this->sites->has($key)) {
            throw new InvalidArgumentException("Unknown Docent site [{$key}].");
        }

        if (! isset($this->services[$key])) {
            $this->services[$key] = $this->buildAll($key);
        }

        return $this->services[$key][$class]
            ?? throw new InvalidArgumentException("Service [{$class}] is not part of the Docent site graph.");
    }

    /** @return array<class-string, object> */
    private function buildAll(string $key): array
    {
        $config = $this->sites->siteConfig($key);
        $path = $config->get('filesystem.path');

        if ($path === null && $key !== 'docs') {
            throw new RuntimeException("Docent site [{$key}] has no filesystem.path configured.");
        }

        if ($path !== null && ! is_string($path)) {
            throw new RuntimeException("Docent site [{$key}] has an invalid filesystem.path.");
        }

        $filesystem = new FilesystemRepository($path ?? $this->app->resourcePath('docs'));
        $database = null;
        $repository = $filesystem;

        if ((bool) $config->get('database.enabled', false)) {
            $connection = $config->get('database.connection');
            $database = new DatabaseRepository(is_string($connection) ? $connection : null, $key);
            $repository = new CompositeRepository($database, $filesystem);
        }

        $store = $config->get('cache.store');
        $prefix = (string) $config->get('cache.prefix', 'docent').':'.$key;
        $cache = new DocentCache(
            $this->app['cache']->store(is_string($store) ? $store : null),
            $prefix,
        );
        $registry = $this->sites->registryFor($key);
        $mode = $this->app->make(DocumentationMode::class);
        $navigation = new NavigationBuilder(
            $repository,
            $registry,
            $cache,
            $config,
            static fn (string $slug): string => $mode->widget()
                ? route(
                    $slug === '' ? "docent.{$key}.widget.home" : "docent.{$key}.widget.show",
                    $slug === '' ? [] : ['slug' => $slug],
                )
                : route(
                    $slug === '' ? "docent.{$key}.home" : "docent.{$key}.show",
                    $slug === '' ? [] : ['slug' => $slug],
                ),
        );
        $codeBlocks = new PhikiCodeBlockRenderer($cache);
        $manager = new DocentManager(
            $registry,
            $repository,
            $this->app->make(DocumentParser::class),
            $cache,
            $navigation,
            $codeBlocks,
            $filesystem,
            $mode,
            $this->app->make(ContentHtmlSanitizer::class),
            $config,
        );
        $indexer = new SearchIndexer($repository, $cache, $manager);
        $stopWords = $config->get('search.stop_words');
        $search = new SearchEngine(
            $indexer,
            $manager,
            new SearchQueryAnalyzer(is_array($stopWords) ? $stopWords : null),
        );
        $retriever = new AiRetriever($search, $indexer, $manager);
        $corpus = new AiCorpusBuilder($manager, $repository, $retriever);
        $answers = new AiAnswerService($manager, $this->app->make(PrismGuard::class), validate: false);
        $questionLogger = new AiQuestionLogger($manager);
        $conversations = new AiConversationStore($cache, $manager);
        $insightRecorder = new InsightRecorder($manager);
        $insightSummary = new InsightSummary($manager);

        $graph = [
            SiteConfig::class => $config,
            FilesystemRepository::class => $filesystem,
            DocumentationRepository::class => $repository,
            DocentCache::class => $cache,
            IntegrationRegistry::class => $registry,
            NavigationBuilder::class => $navigation,
            CodeBlockRenderer::class => $codeBlocks,
            PhikiCodeBlockRenderer::class => $codeBlocks,
            DocentManager::class => $manager,
            SearchIndexer::class => $indexer,
            SearchEngine::class => $search,
            AiRetriever::class => $retriever,
            AiCorpusBuilder::class => $corpus,
            AiAnswerService::class => $answers,
            AiQuestionLogger::class => $questionLogger,
            AiConversationStore::class => $conversations,
            InsightRecorder::class => $insightRecorder,
            InsightSummary::class => $insightSummary,
        ];

        if ($database !== null) {
            $graph[DatabaseRepository::class] = $database;
            $graph[CompositeRepository::class] = $repository;
        }

        return $graph;
    }
}
