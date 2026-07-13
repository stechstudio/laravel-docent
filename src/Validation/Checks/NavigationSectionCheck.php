<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Validates the filesystem/database group metadata that promotes top-level
 * directories into navigation sections.
 */
final class NavigationSectionCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        $directories = [];

        foreach ($context->pages() as $page) {
            $directory = $page->directory;

            while ($directory !== '') {
                $directories[$directory] = true;
                $directory = str_contains($directory, '/')
                    ? substr($directory, 0, (int) strrpos($directory, '/'))
                    : '';
            }
        }

        if (is_dir($context->docsPath())) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $context->docsPath(),
                RecursiveDirectoryIterator::SKIP_DOTS,
            ));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getFilename() === '_group.yml') {
                    $relative = str_replace('\\', '/', substr($file->getPath(), strlen(rtrim($context->docsPath(), DIRECTORY_SEPARATOR)) + 1));

                    if ($relative !== '') {
                        $directories[$relative] = true;
                    }
                }
            }
        }

        foreach (array_keys($directories) as $directory) {
            if (($context->repository()->groupMeta($directory)['section'] ?? false) !== true) {
                continue;
            }

            if (str_contains($directory, '/')) {
                yield Issue::warning(
                    'invalid-section-depth',
                    $directory.'/_group.yml',
                    'Only top-level directories can be navigation sections; section: true is ignored here.',
                    1,
                );

                continue;
            }

            $hasPages = false;

            foreach ($context->pages() as $page) {
                if ($page->directory === $directory || str_starts_with($page->directory, $directory.'/')) {
                    $hasPages = true;
                    break;
                }
            }

            if (! $hasPages) {
                yield Issue::warning(
                    'empty-section',
                    $directory.'/_group.yml',
                    'Promoted section "'.$directory.'" has no pages and will not appear in navigation.',
                    1,
                );
            }
        }
    }
}
