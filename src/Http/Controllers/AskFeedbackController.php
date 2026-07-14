<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use STS\Docent\Ai\Models\AiQuestion;

final class AskFeedbackController
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'feedback_token' => ['required', 'string'],
            'thumbs' => ['required', 'in:up,down'],
        ]);

        $question = AiQuestion::query()->findOrFail((int) $validated['question_id']);

        abort_unless(hash_equals($question->feedbackToken(), (string) $validated['feedback_token']), 403);

        $question->forceFill(['thumbs' => $validated['thumbs']])->save();

        return response()->noContent();
    }
}
