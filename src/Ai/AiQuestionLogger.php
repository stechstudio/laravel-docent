<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

use STS\Docent\Ai\Models\AiQuestion;
use STS\Docent\Runtime\DocumentationContext;

final class AiQuestionLogger
{
    public function start(string $question, DocumentationContext $context): ?AiQuestion
    {
        if (! config('docent.ai.log_questions', true)) {
            return null;
        }

        return AiQuestion::query()->create([
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
}
