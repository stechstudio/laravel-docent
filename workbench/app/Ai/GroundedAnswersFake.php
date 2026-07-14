<?php

declare(strict_types=1);

namespace Workbench\App\Ai;

use Generator;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Testing\PrismFake;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Text\Request as TextRequest;

final class GroundedAnswersFake extends PrismFake
{
    /** @return Generator<StreamEvent> */
    public function stream(TextRequest $request): Generator
    {
        $this->recorded[] = $request;
        $prompt = implode("\n", array_map(
            static fn ($message): string => $message->content,
            $request->systemPrompts(),
        ));

        preg_match('/^- (https?:\/\/\S+\/getting-started\/quickstart) — Quickstart$/m', $prompt, $match);
        $quickstart = $match[1] ?? 'the Quickstart page';

        $response = TextResponseFake::make()->withText(
            'Start with the [Quickstart guide]('.$quickstart.') for installation and your first page.',
        );

        yield from $this->streamEventsFromTextResponse($response, $request);
    }
}
