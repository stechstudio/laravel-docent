<?php

declare(strict_types=1);

namespace STS\Docent\Content\Repositories;

use STS\Docent\Content\DocumentSource;

/**
 * Composes an ordered list of repositories into one. The first child that can
 * answer a lookup wins, so earlier children override later ones — the default
 * wiring places the database store ahead of the filesystem. {@see shadowed()}
 * surfaces the drift: file pages an earlier store has taken over.
 */
final class CompositeRepository implements DocumentationRepository
{
    /** @var list<DocumentationRepository> */
    private readonly array $children;

    public function __construct(DocumentationRepository ...$children)
    {
        $this->children = array_values($children);
    }

    public function find(string $slug): ?DocumentSource
    {
        foreach ($this->children as $child) {
            if (($source = $child->find($slug)) !== null) {
                return $source;
            }
        }

        return null;
    }

    public function all(): iterable
    {
        $seen = [];

        foreach ($this->children as $child) {
            foreach ($child->all() as $reference) {
                if (isset($seen[$reference->slug])) {
                    continue;
                }

                $seen[$reference->slug] = true;

                yield $reference;
            }
        }
    }

    public function partial(string $name): ?DocumentSource
    {
        foreach ($this->children as $child) {
            if (($source = $child->partial($name)) !== null) {
                return $source;
            }
        }

        return null;
    }

    public function groupMeta(string $directory): ?array
    {
        foreach ($this->children as $child) {
            if (($meta = $child->groupMeta($directory)) !== null) {
                return $meta;
            }
        }

        return null;
    }

    public function directoryHash(): string
    {
        return sha1(implode('|', array_map(
            static fn (DocumentationRepository $child): string => $child->directoryHash(),
            $this->children,
        )));
    }

    /**
     * Slugs an earlier child serves that also exist in a later child — the file
     * pages shadowed by a database override. One entry per shadowed slug.
     *
     * @return list<string>
     */
    public function shadowed(): array
    {
        $slugSets = array_map(
            static function (DocumentationRepository $child): array {
                $slugs = [];

                foreach ($child->all() as $reference) {
                    $slugs[$reference->slug] = true;
                }

                return $slugs;
            },
            $this->children,
        );

        $shadowed = [];

        foreach ($slugSets as $index => $slugs) {
            foreach ($slugs as $slug => $_) {
                foreach ($slugSets as $laterIndex => $laterSlugs) {
                    if ($laterIndex > $index && isset($laterSlugs[$slug])) {
                        $shadowed[$slug] = true;
                        break;
                    }
                }
            }
        }

        return array_keys($shadowed);
    }
}
