<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\Response;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentManager;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Support\DocentCache;

final class SitemapController
{
    public function __invoke(
        DocentManager $docent,
        DocumentationRepository $repository,
        DocentCache $cache,
        NavigationBuilder $navigation,
    ): Response {
        abort_unless($docent->config('seo.sitemap', true), 404);

        $key = 'sitemap:'.$repository->directoryHash();

        $content = $cache->remember($key, function () use ($docent, $navigation): string {
            $slugs = [''];

            foreach ($docent->navigationSections($docent->guestContext()) as $section) {
                foreach ($navigation->flatten($section->navigation) as $item) {
                    if (! $item->searchExcluded) {
                        $slugs[] = $item->slug;
                    }
                }
            }

            $urls = array_map(
                static fn (string $slug): string => '<url><loc>'.e($docent->fullUrl($slug)).'</loc></url>',
                $slugs,
            );

            return '<?xml version="1.0" encoding="UTF-8"?>'
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                .implode('', $urls)
                .'</urlset>';
        });

        return response($content, 200, ['Content-Type' => 'application/xml']);
    }
}
