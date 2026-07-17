<?php

declare(strict_types=1);

namespace STS\Docent\Sites;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use STS\Docent\DocentManager;
use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\IntegrationRegistry;

/**
 * Durable facade root for configured Docent sites and their integrations.
 *
 * @mixin DocentManager
 */
final class SiteRegistry
{
    /** @var array<string, IntegrationRegistry> */
    private array $registries = [];

    public function __construct(
        private readonly Application $app,
        private readonly IntegrationRegistry $global,
    ) {}

    /** @return list<string> */
    public function keys(): array
    {
        $keys = array_map(
            static fn (int|string $key): string => (string) $key,
            array_keys((array) $this->app['config']->get('docent.sites', [])),
        );

        foreach ($keys as $key) {
            $this->validateKey($key);
        }

        return $keys;
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    public function defaultKey(): string
    {
        $configured = $this->app['config']->get('docent.default');

        if (is_string($configured) && $configured !== '') {
            if (! $this->has($configured)) {
                throw new InvalidArgumentException("Unknown default Docent site [{$configured}].");
            }

            return $configured;
        }

        return $this->keys()[0] ?? throw new InvalidArgumentException('No Docent sites are configured.');
    }

    public function current(): DocentManager
    {
        return $this->app->make(SiteServices::class)->current();
    }

    public function site(?string $key = null): DocentManager
    {
        return $this->app->make(SiteServices::class)->site($key);
    }

    public function service(string $class): object
    {
        return $this->app->make(SiteServices::class)->service($class);
    }

    public function serviceFor(string $key, string $class): object
    {
        return $this->app->make(SiteServices::class)->serviceFor($key, $class);
    }

    public function siteConfig(string $key): SiteConfig
    {
        $this->ensureKnown($key);

        return new SiteConfig($key, (array) $this->app['config']->get('docent', []));
    }

    public function registryFor(string $key): IntegrationRegistry
    {
        $this->ensureKnown($key);

        return $this->registries[$key] ??= new IntegrationRegistry(
            fn (string $class): object => $this->app->make($class),
            $this->global,
        );
    }

    /** @param Closure|class-string $resolver */
    public function condition(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->global->condition($name, $resolver, $label, $description);

        return $this;
    }

    /** @param Closure|class-string $resolver */
    public function value(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->global->value($name, $resolver, $label, $description);

        return $this;
    }

    /** @param Closure|class-string $resolver */
    public function link(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->global->link($name, $resolver, $label, $description);

        return $this;
    }

    /** @param Closure|class-string|DocumentationComponent $resolver */
    public function component(string $name, Closure|string|DocumentationComponent $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->global->component($name, $resolver, $label, $description);

        return $this;
    }

    /** @param Closure|class-string $resolver */
    public function audience(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->global->audience($name, $resolver, $label, $description);

        return $this;
    }

    /** @param list<string> $slugs */
    public function suggest(string $pattern, array $slugs): self
    {
        $this->global->suggest($pattern, $slugs);

        return $this;
    }

    /** @param array<int|string, mixed> $arguments */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->current()->{$method}(...$arguments);
    }

    private function ensureKnown(string $key): void
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Unknown Docent site [{$key}].");
        }
    }

    private function validateKey(string $key): void
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $key) !== 1) {
            throw new InvalidArgumentException("Invalid Docent site key [{$key}]. Site keys may contain only letters, numbers, underscores, and hyphens.");
        }
    }
}
