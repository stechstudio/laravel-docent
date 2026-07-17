<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

use Generator;
use LogicException;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use STS\Docent\DocentManager;

final class AiAnswerService
{
    private ?string $provider = null;

    private ?string $model = null;

    public function __construct(
        private readonly DocentManager $docent,
        private readonly PrismGuard $guard,
        bool $validate = true,
    ) {
        if ($validate) {
            $this->credentials();
        }
    }

    /** @return Generator<object> */
    /** @param list<AiConversationTurn> $history */
    public function stream(AiCorpus $corpus, string $question, array $history = []): Generator
    {
        $facade = 'Prism\\Prism\\Facades\\Prism';
        $messages = [];
        [$provider, $model] = $this->credentials();

        foreach ($history as $turn) {
            $messages[] = new UserMessage($turn->question);
            $messages[] = new AssistantMessage($turn->answer);
        }

        $messages[] = new UserMessage(AiPrompt::question($question));
        $request = $facade::text()
            ->using($provider, $model)
            ->withSystemPrompt(AiPrompt::system($corpus))
            ->withMessages($messages)
            ->withMaxTokens(max(1, (int) $this->docent->config('ai.max_tokens', 1200)));

        yield from $request->asStream();
    }

    /** @return array{string, string} */
    private function credentials(): array
    {
        if ($this->provider !== null && $this->model !== null) {
            return [$this->provider, $this->model];
        }

        $this->guard->ensureInstalled();

        $provider = trim((string) $this->docent->config('ai.provider'));
        $model = trim((string) $this->docent->config('ai.model'));

        if ($provider === '' || $model === '') {
            throw new LogicException('Docent AI requires both docent.ai.provider and docent.ai.model.');
        }

        return [$this->provider = $provider, $this->model = $model];
    }
}
