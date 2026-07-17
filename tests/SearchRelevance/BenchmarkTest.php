<?php

declare(strict_types=1);

use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Content\Repositories\FilesystemRepository;
use STS\Docent\DocentManager;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Search\SearchEngine;
use STS\Docent\Search\SearchIndexer;

it('keeps warm ranked search under the documented target on 1000 generated pages', function () {
    $root = sys_get_temp_dir().'/docent-search-benchmark-'.bin2hex(random_bytes(5));
    mkdir($root, 0777, true);

    for ($index = 0; $index < 1000; $index++) {
        $number = str_pad((string) $index, 4, '0', STR_PAD_LEFT);
        file_put_contents($root.'/page-'.$number.'.md', <<<MD
        ---
        title: Guide {$number}
        description: Help for workflow {$number}.
        ---

        Common setup instructions for uniqueterm{$number} and account settings.
        MD);
    }

    $this->beforeApplicationDestroyed(function () use ($root): void {
        foreach (glob($root.'/*.md') ?: [] as $file) {
            unlink($file);
        }
        rmdir($root);
    });

    config()->set('docent.sites.docs.filesystem.path', $root);
    app()->instance(DocumentationRepository::class, new FilesystemRepository($root));
    foreach ([NavigationBuilder::class, DocentManager::class, SearchIndexer::class, SearchEngine::class] as $service) {
        app()->forgetInstance($service);
    }

    $engine = app(SearchEngine::class);
    $context = $this->contextFor(null);
    expect(app(SearchIndexer::class)->index()->records)->toHaveCount(1000);

    // Index construction is intentionally outside the measurement. Real
    // requests reuse the cached index, so the product target is warm latency.
    $started = hrtime(true);
    for ($iteration = 0; $iteration < 10; $iteration++) {
        $results = $engine->search('how do I configure uniqueterm0420', $context);
        expect($results[0]?->slug)->toBe('page-0420');
    }
    $averageMilliseconds = ((hrtime(true) - $started) / 1_000_000) / 10;

    expect($averageMilliseconds)->toBeLessThan(50.0);
})->skip(getenv('DOCENT_BENCHMARK') !== '1', 'Set DOCENT_BENCHMARK=1 to run the local performance contract.');
