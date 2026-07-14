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

        preg_match('/^- (https?:\/\/\S+\/getting-started\/content-components) — /m', $prompt, $videoMatch);
        $components = $videoMatch[1] ?? $quickstart;

        $question = mb_strtolower((string) $request->prompt());
        $answer = str_contains($question, 'video')
            ? <<<MARKDOWN
            ## Add a video

            Use Docent's `video` component with a provider URL or a self-hosted file:

            ```markdown
            :::video src="https://www.youtube.com/watch?v=example" title="Product tour"
            :::
            ```

            Provider videos wait for a click before contacting the video host. See [Videos in the authoring toolkit]({$components}) for every option.
            MARKDOWN
            : 'Start with the [Quickstart guide]('.$quickstart.') for installation and your first page.';

        $response = TextResponseFake::make()->withText(
            $answer,
        );

        yield from $this->streamEventsFromTextResponse($response, $request);
    }
}
