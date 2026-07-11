<?php

declare(strict_types=1);

namespace STS\Docent\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository;

/**
 * Versioned, store-agnostic cache for parsed ASTs, navigation, and search.
 *
 * Every key is namespaced with a version stamp; `docent:clear` bumps the stamp,
 * orphaning (and thereby invalidating) all prior entries without needing cache
 * tags or a full flush.
 */
final class DocentCache
{
    public function __construct(
        private readonly Repository $store,
        private readonly string $prefix = 'docent',
    ) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $key, Closure $callback): mixed
    {
        return $this->store->rememberForever($this->key($key), $callback);
    }

    public function version(): int
    {
        return (int) $this->store->get($this->prefix.':version', 1);
    }

    public function bump(): void
    {
        $this->store->forever($this->prefix.':version', $this->version() + 1);
    }

    private function key(string $suffix): string
    {
        return $this->prefix.':'.$this->version().':'.$suffix;
    }
}
