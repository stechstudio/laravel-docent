<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use STS\Docent\Ai\Models\AiQuestion;
use STS\Docent\DocentManager;
use STS\Docent\Insights\InsightRecorder;

final class AskFeedbackController
{
    public function __construct(
        private readonly InsightRecorder $insights,
        private readonly DocentManager $docent,
    ) {}

    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'feedback_token' => ['required', 'string'],
            'thumbs' => ['required', 'in:up,down'],
        ]);

        $connection = $this->docent->config('database.connection');
        $question = AiQuestion::forSite(
            is_string($connection) ? $connection : null,
            $this->docent->key(),
        )->findOrFail((int) $validated['question_id']);

        abort_unless(hash_equals($question->feedbackToken(), (string) $validated['feedback_token']), 403);

        $question->forceFill(['thumbs' => $validated['thumbs']])->save();
        $this->insights->assistantFeedback($question, (string) $validated['thumbs']);

        return response()->noContent();
    }
}
