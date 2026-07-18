<?php

declare(strict_types=1);

namespace STS\Docent\Sites;

use Illuminate\Container\Container;
use InvalidArgumentException;

/** Holds the selected site key for the current application scope. */
final class CurrentSite
{
    private ?string $selected = null;

    public function __construct(
        private readonly SiteRegistry $sites,
        private readonly Container $container,
    ) {}

    public function set(string $key): void
    {
        if (! $this->sites->has($key)) {
            throw new InvalidArgumentException("Unknown Docent site [{$key}].");
        }

        $changed = $key !== ($this->selected ?? $this->sites->defaultKey());
        $this->selected = $key;

        if ($changed) {
            $this->forgetSiteAliases();
        }
    }

    public function key(): string
    {
        if ($this->selected !== null && ! $this->sites->has($this->selected)) {
            throw new InvalidArgumentException("Unknown Docent site [{$this->selected}].");
        }

        return $this->selected ?? $this->sites->defaultKey();
    }

    /**
     * Anything that resolved a per-site alias before this selection — a
     * host's global middleware, an early listener — memoized the previously
     * effective site's instance in the request scope. Forget those instances
     * so later resolutions rebind to the newly selected site instead of
     * serving another site's services for the rest of the request.
     */
    private function forgetSiteAliases(): void
    {
        foreach (SiteServices::ALIASES as $class) {
            $this->container->forgetInstance($class);
        }
    }
}
