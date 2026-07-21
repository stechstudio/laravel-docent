<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;
use STS\Docent\Validation\OptInCheck;

final class DescriptionLengthCheck implements OptInCheck
{
    private const MAX = 160;

    public function rule(): string
    {
        return 'description-length';
    }

    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $description = $page->description;

            if (is_string($description) && mb_strlen($description) > self::MAX) {
                yield Issue::warning(
                    'description-length',
                    $page->slug,
                    'Description is '.mb_strlen($description).' characters; keep it under '.self::MAX.' for SEO and search snippets.',
                    null,
                );
            }
        }
    }
}
