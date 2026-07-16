<?php

declare(strict_types=1);

namespace STS\Docent\Content\Repositories;

use STS\Docent\Content\DocumentSource;
use STS\Docent\Documents\FrontMatter;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Composes an ordered list of repositories into one. The first child that can
 * answer a lookup wins, so earlier children override later ones — the default
 * wiring places the database store ahead of the filesystem. {@see shadowed()}
 * surfaces the drift: file pages an earlier store has taken over.
 */
final class CompositeRepository implements DocumentationRepository, RedirectCollisionRepository
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
            if ($child instanceof LockAwareRepository && $child->pageLocked($slug)) {
                return $child->find($slug);
            }
        }

        $redirect = null;

        foreach ($this->children as $child) {
            $source = $child->find($slug);

            if ($source === null) {
                continue;
            }

            if (! $this->isRedirect($source)) {
                return $source;
            }

            $redirect ??= $source;
        }

        return $redirect;
    }

    public function all(): iterable
    {
        $references = [];
        $order = [];
        $lockedOwners = $this->lockedPageOwners();

        foreach ($this->children as $index => $child) {
            foreach ($child->all() as $reference) {
                if (isset($lockedOwners[$reference->slug]) && $lockedOwners[$reference->slug] !== $index) {
                    continue;
                }

                if (! isset($references[$reference->slug])) {
                    $order[] = $reference->slug;
                    $references[$reference->slug] = $reference;
                } elseif ($references[$reference->slug]->redirectStub && ! $reference->redirectStub) {
                    $references[$reference->slug] = $reference;
                }
            }
        }

        foreach ($order as $slug) {
            yield $references[$slug];
        }
    }

    public function redirectCollisions(): array
    {
        $types = [];
        $collisions = [];

        foreach ($this->children as $child) {
            if ($child instanceof RedirectCollisionRepository) {
                foreach ($child->redirectCollisions() as $slug) {
                    $collisions[$slug] = true;
                }
            }

            foreach ($child->all() as $reference) {
                $types[$reference->slug][$reference->redirectStub ? 'redirect' : 'page'] = true;
            }
        }

        foreach ($types as $slug => $found) {
            if (isset($found['redirect'], $found['page'])) {
                $collisions[$slug] = true;
            }
        }

        return array_keys($collisions);
    }

    public function partial(string $name): ?DocumentSource
    {
        foreach ($this->children as $child) {
            if ($child instanceof LockAwareRepository && $child->partialLocked($name)) {
                return $child->partial($name);
            }
        }

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
        $slugSets = $this->slugSets();
        $lockedOwners = $this->lockedPageOwners();

        $shadowed = [];

        foreach ($slugSets as $index => $slugs) {
            foreach ($slugs as $slug => $_) {
                if (isset($lockedOwners[$slug])) {
                    continue;
                }

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

    /**
     * Locked slugs that also exist in another repository. The locked source is
     * served, but the ignored row remains useful drift for `docent:check` to
     * surface.
     *
     * @return list<string>
     */
    public function lockedShadowed(): array
    {
        $slugSets = array_map(
            static function (DocumentationRepository $child): array {
                $slugs = [];

                $stored = $child instanceof StoredPageRepository
                    ? $child->storedSlugs()
                    : array_map(static fn ($reference): string => $reference->slug, [...$child->all()]);

                foreach ($stored as $slug) {
                    $slugs[$slug] = true;
                }

                return $slugs;
            },
            $this->children,
        );
        $shadowed = [];

        foreach ($this->lockedPageOwners() as $slug => $owner) {
            foreach ($slugSets as $index => $slugs) {
                if ($index !== $owner && isset($slugs[$slug])) {
                    $shadowed[] = $slug;
                    break;
                }
            }
        }

        foreach ($slugSets as $index => $slugs) {
            foreach ($slugs as $slug => $_) {
                if (! str_starts_with($slug, '_partials/')) {
                    continue;
                }

                $name = substr($slug, strlen('_partials/'));

                foreach ($this->children as $ownerIndex => $child) {
                    if ($ownerIndex !== $index && $child instanceof LockAwareRepository && $child->partialLocked($name)) {
                        $shadowed[] = $slug;
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($shadowed));
    }

    /** @return array<string, int> */
    private function lockedPageOwners(): array
    {
        $owners = [];

        foreach ($this->children as $index => $child) {
            if (! $child instanceof LockAwareRepository) {
                continue;
            }

            foreach ($child->all() as $reference) {
                if ($reference->locked) {
                    $owners[$reference->slug] = $index;
                }
            }
        }

        return $owners;
    }

    /** @return list<array<string, true>> */
    private function slugSets(): array
    {
        return array_map(
            static function (DocumentationRepository $child): array {
                $slugs = [];

                foreach ($child->all() as $reference) {
                    $slugs[$reference->slug] = true;
                }

                return $slugs;
            },
            $this->children,
        );
    }

    private function isRedirect(DocumentSource $source): bool
    {
        $data = $source->frontMatter;

        if ($data === null && str_starts_with($source->rawContent, '---')
            && preg_match('/^---\R(.*?)\R---\s*(?:\R|$)/s', $source->rawContent, $matches) === 1) {
            try {
                $parsed = Yaml::parse($matches[1]);
                $data = is_array($parsed) ? $parsed : [];
            } catch (ParseException) {
                $data = [];
            }
        }

        return (new FrontMatter($data ?? []))->hasRedirect();
    }
}
