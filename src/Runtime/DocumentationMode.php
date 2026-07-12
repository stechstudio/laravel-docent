<?php

declare(strict_types=1);

namespace STS\Docent\Runtime;

/** Request-scoped rendering mode shared by routes, navigation, and renderers. */
final class DocumentationMode
{
    private bool $widget = false;

    public function enableWidget(): void
    {
        $this->widget = true;
    }

    public function widget(): bool
    {
        return $this->widget;
    }
}
