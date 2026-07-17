<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

use STS\Docent\Ai\Models\AiQuestion;
use STS\Docent\DocentManager;
use STS\Docent\Runtime\DocumentationContext;

final class AiQuestionLogger
{
    public function __construct(
        private readonly DocentManager $docent,
    ) {}

    public function start(string $question, DocumentationContext $context): ?AiQuestion
    {
        if (! $this->docent->config('ai.log_questions', true)) {
            return null;
        }

        return AiQuestion::forSite($this->connection(), $this->docent->key())->create([
            'site' => $this->docent->key(),
            'question' => $question,
            'status' => 'no-answer',
            'viewer_class' => $context->user === null ? 'guest' : 'authenticated',
        ]);
    }

    public function finish(?AiQuestion $question, string $answer): void
    {
        if ($question === null) {
            return;
        }

        $normalized = trim(preg_replace('/\s+/', ' ', $answer) ?? $answer);
        $question->forceFill([
            'status' => $normalized === '' ? 'no-answer' : 'answered',
            'answer_hash' => $normalized === '' ? null : hash('sha256', $normalized),
        ])->save();
    }

    private function connection(): ?string
    {
        $connection = $this->docent->config('database.connection');

        return is_string($connection) ? $connection : null;
    }
}
