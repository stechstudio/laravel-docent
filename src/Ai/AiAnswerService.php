<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

use Generator;
use LogicException;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

final class AiAnswerService
{
    private string $provider;

    private string $model;

    public function __construct(PrismGuard $guard)
    {
        $guard->ensureInstalled();

        $this->provider = trim((string) config('docent.ai.provider'));
        $this->model = trim((string) config('docent.ai.model'));

        if ($this->provider === '' || $this->model === '') {
            throw new LogicException('Docent AI requires both docent.ai.provider and docent.ai.model.');
        }
    }

    /** @return Generator<object> */
    /** @param list<AiConversationTurn> $history */
    public function stream(AiCorpus $corpus, string $question, array $history = []): Generator
    {
        $facade = 'Prism\\Prism\\Facades\\Prism';
        $messages = [];

        foreach ($history as $turn) {
            $messages[] = new UserMessage($turn->question);
            $messages[] = new AssistantMessage($turn->answer);
        }

        $messages[] = new UserMessage(AiPrompt::question($question));
        $request = $facade::text()
            ->using($this->provider, $this->model)
            ->withSystemPrompt(AiPrompt::system($corpus))
            ->withMessages($messages)
            ->withMaxTokens(max(1, (int) config('docent.ai.max_tokens', 1200)));

        yield from $request->asStream();
    }
}
