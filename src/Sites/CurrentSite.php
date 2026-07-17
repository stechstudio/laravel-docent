<?php

declare(strict_types=1);

namespace STS\Docent\Sites;

use InvalidArgumentException;

/** Holds the selected site key for the current application scope. */
final class CurrentSite
{
    private ?string $selected = null;

    public function __construct(private readonly SiteRegistry $sites) {}

    public function set(string $key): void
    {
        if (! $this->sites->has($key)) {
            throw new InvalidArgumentException("Unknown Docent site [{$key}].");
        }

        $this->selected = $key;
    }

    public function key(): string
    {
        if ($this->selected !== null && ! $this->sites->has($this->selected)) {
            throw new InvalidArgumentException("Unknown Docent site [{$this->selected}].");
        }

        return $this->selected ?? $this->sites->defaultKey();
    }
}
