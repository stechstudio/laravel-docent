<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser;

use STS\Docent\Documents\Document;

interface DocumentParser
{
    /**
     * Parse raw source (markdown + front matter) into a Docent document.
     */
    public function parse(string $content): Document;
}
