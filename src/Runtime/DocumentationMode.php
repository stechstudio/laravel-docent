<?php

declare(strict_types=1);

namespace STS\Docent\Runtime;

/** Request-scoped rendering mode shared by routes, navigation, and renderers. */
final class DocumentationMode
{
    private bool $widget = false;

    private ?int $shareExpiryDay = null;

    public function enableWidget(): void
    {
        $this->widget = true;
    }

    public function widget(): bool
    {
        return $this->widget;
    }

    /**
     * Mark this request as a share render, authorized until `$expiryDay`.
     * Assets emitted during the render inherit that day, so a shared page and
     * its images expire together.
     */
    public function enableShare(int $expiryDay): void
    {
        $this->shareExpiryDay = $expiryDay;
    }

    public function share(): bool
    {
        return $this->shareExpiryDay !== null;
    }

    public function shareExpiryDay(): ?int
    {
        return $this->shareExpiryDay;
    }
}
