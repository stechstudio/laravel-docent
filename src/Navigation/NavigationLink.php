<?php

declare(strict_types=1);

namespace STS\Docent\Navigation;

use STS\Docent\Support\Icon;

/**
 * A configured utility link resolved for the current viewer and surface.
 */
final class NavigationLink
{
    public function __construct(
        public readonly string $label,
        public readonly string $url,
        public readonly ?string $icon = null,
        public readonly bool $iconIsImage = false,
        public readonly bool $external = false,
        public readonly bool $active = false,
    ) {}

    public function iconMarkup(): ?string
    {
        if ($this->icon === null) {
            return null;
        }

        return $this->iconIsImage
            ? '<img src="'.e($this->icon).'" alt="" />'
            : Icon::svg($this->icon);
    }
}
