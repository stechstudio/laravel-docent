<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use STS\Docent\Ai\AiAnswerRenderer;
use STS\Docent\Ai\AiAnswerService;
use STS\Docent\Ai\AiConversation;
use STS\Docent\Ai\AiConversationStore;
use STS\Docent\Ai\AiCorpus;
use STS\Docent\Ai\AiCorpusBuilder;
use STS\Docent\Ai\AiQuestionLogger;
use STS\Docent\Ai\Conversation\AiConversationBusy;
use STS\Docent\Ai\Conversation\AiConversationExpired;
use STS\Docent\Ai\Conversation\AiConversationForbidden;
use STS\Docent\Ai\Conversation\AiConversationResolution;
use STS\Docent\Ai\Models\AiQuestion;
use STS\Docent\DocentManager;
use STS\Docent\Insights\InsightRecorder;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Support\DocentCache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class AskController
{
    public function __construct(
        private readonly DocentManager $docent,
        private readonly AiCorpusBuilder $corpus,
        private readonly AiAnswerService $answers,
        private readonly AiAnswerRenderer $renderer,
        private readonly AiQuestionLogger $questions,
        private readonly DocentCache $cache,
        private readonly AiConversationStore $conversations,
        private readonly InsightRecorder $insights,
    ) {}

    public function __invoke(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'conversation_id' => ['nullable', 'uuid', 'required_with:conversation_token'],
            'conversation_token' => ['nullable', 'string', 'size:64', 'required_with:conversation_id'],
            'regenerate' => ['sometimes', 'boolean'],
            'current_slug' => ['nullable', 'string', 'max:255'],
        ]);

        if (($limited = $this->rateLimit($request)) !== null) {
            return $limited;
        }

        $question = $this->normalize((string) $validated['question']);
        $widget = $request->string('mode')->toString() === 'widget' && $this->docent->config('widget.enabled', false);
        $mode = $widget ? 'widget' : 'reader';

        if ($widget) {
            $this->docent->enableWidgetMode();
        }

        $context = $this->docent->contextFor($request);
        $corpusVersion = $this->corpus->version($context, $widget);

        $resolution = $this->resolveConversation($request, $context, $corpusVersion, $mode, $validated);

        if ($resolution instanceof JsonResponse) {
            return $resolution;
        }

        $conversation = $resolution->conversation;
        $regenerate = (bool) ($validated['regenerate'] ?? false);

        if ($regenerate) {
            $conversation = $this->withoutRegeneratedTurn($conversation, $question);

            if ($conversation instanceof JsonResponse) {
                return $conversation;
            }
        }

        $currentSlug = trim((string) ($validated['current_slug'] ?? ''));
        $corpus = $this->corpus->build(
            $context,
            $question,
            $conversation->turns,
            $currentSlug,
            $widget,
        );

        try {
            $this->conversations->acquire($resolution->conversation);
        } catch (AiConversationBusy $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'conversation_busy'], 409);
        }

        $log = $this->questions->start($question, $context);
        $cacheKey = $this->cacheKey($corpus, $conversation, $question, $mode);
        $cached = $regenerate ? null : $this->cache->get($cacheKey);

        if (is_array($cached) && is_string($cached['answer'] ?? null)) {
            $this->questions->finish($log, $cached['answer']);
            $this->insights->assistantOutcome($question, $cached['answer'], $corpus, $mode, $currentSlug, $log);

            return $this->cachedResponse($corpus, $resolution, $conversation, $question, $log, $cached['answer']);
        }

        return $this->liveResponse($corpus, $resolution, $conversation, $question, $cacheKey, $log, $mode, $currentSlug);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveConversation(Request $request, DocumentationContext $context, string $corpusVersion, string $mode, array $validated): AiConversationResolution|JsonResponse
    {
        try {
            return $this->conversations->resolve(
                $request,
                $context,
                $corpusVersion,
                $mode,
                isset($validated['conversation_id']) ? (string) $validated['conversation_id'] : null,
                isset($validated['conversation_token']) ? (string) $validated['conversation_token'] : null,
            );
        } catch (AiConversationForbidden $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (AiConversationExpired $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'conversation_expired'], 409);
        }
    }

    private function withoutRegeneratedTurn(AiConversation $conversation, string $question): AiConversation|JsonResponse
    {
        $last = $conversation->turns[array_key_last($conversation->turns)] ?? null;

        if ($last === null || ! hash_equals($last->question, $question)) {
            return response()->json(['message' => 'Only the most recent answer can be regenerated.'], 422);
        }

        return $conversation->withoutLastTurn();
    }

    private function cachedResponse(
        AiCorpus $corpus,
        AiConversationResolution $resolution,
        AiConversation $conversation,
        string $question,
        ?AiQuestion $log,
        string $answer,
    ): StreamedResponse {
        return $this->eventStream(function () use ($corpus, $resolution, $conversation, $question, $log, $answer): void {
            try {
                $this->emitConversation($resolution, $conversation);
                $this->emit('citations', $this->citationsPayload($corpus, $log));

                if ($answer !== '') {
                    $this->emit('text_delta', ['delta' => $answer]);
                    $this->emit('answer_rendered', ['html' => $this->renderer->render($answer, $corpus->citations)]);
                }

                $committed = $this->commit($conversation, $question, $answer);
                $this->emit('stream_end', ['finish_reason' => 'Stop', 'cached' => true, 'committed' => $committed]);
            } finally {
                $this->conversations->release($resolution->conversation);
            }
        });
    }

    private function liveResponse(
        AiCorpus $corpus,
        AiConversationResolution $resolution,
        AiConversation $conversation,
        string $question,
        string $cacheKey,
        ?AiQuestion $log,
        string $mode,
        string $currentSlug,
    ): StreamedResponse {
        return $this->eventStream(function () use ($corpus, $resolution, $conversation, $question, $cacheKey, $log, $mode, $currentSlug): void {
            try {
                $this->emitConversation($resolution, $conversation);
                $this->emit('citations', $this->citationsPayload($corpus, $log));
                $answer = '';
                $failed = false;
                $streamEnd = ['finish_reason' => 'Stop'];

                try {
                    foreach ($this->answers->stream($corpus, $question, $conversation->turns) as $event) {
                        if ($event instanceof TextDeltaEvent) {
                            $answer .= $event->delta;
                        }

                        if ($event instanceof StreamEndEvent) {
                            $streamEnd = $event->toArray();

                            continue;
                        }

                        $this->emit($event->eventKey(), $event->toArray());
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $failed = true;
                    $this->emit('error', ['message' => 'The documentation answer could not be generated.']);
                }

                $this->questions->finish($log, $failed ? '' : $answer);
                $this->insights->assistantOutcome($question, $failed ? '' : $answer, $corpus, $mode, $currentSlug, $log);
                $committed = false;

                if (! $failed && $answer !== '') {
                    $this->cache->put($cacheKey, ['answer' => $answer], max(1, (int) $this->docent->config('ai.answer_ttl', 300)));
                    $this->emit('answer_rendered', ['html' => $this->renderer->render($answer, $corpus->citations)]);
                    $committed = $this->commit($conversation, $question, $answer);
                }

                $this->emit('stream_end', [...$streamEnd, 'committed' => $committed]);
            } finally {
                $this->conversations->release($resolution->conversation);
            }
        });
    }

    private function commit(AiConversation $conversation, string $question, string $answer): bool
    {
        if ($answer === '') {
            return false;
        }

        $this->conversations->save($conversation->withTurn(
            $question,
            $answer,
            max(1, (int) $this->docent->config('ai.conversation.ttl', 7200)),
            max(1, (int) $this->docent->config('ai.conversation.max_turns', 10)),
            max(1, (int) $this->docent->config('ai.conversation.history_budget', 12000)),
        ));

        return true;
    }

    private function emitConversation(AiConversationResolution $resolution, AiConversation $conversation): void
    {
        $this->emit('conversation', [
            'conversation_id' => $resolution->conversation->id,
            'conversation_token' => $resolution->token,
            'expires_at' => time() + max(1, (int) $this->docent->config('ai.conversation.ttl', 7200)),
            'turn_index' => $conversation->turnCount + 1,
            'reset_reason' => $resolution->resetReason,
        ]);
    }

    private function cacheKey(AiCorpus $corpus, AiConversation $conversation, string $question, string $mode): string
    {
        $history = array_map(static fn ($turn): array => [$turn->question, $turn->answer], $conversation->turns);
        $historyHash = hash('sha256', json_encode($history, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return implode(':', [
            'ai-answer-v3',
            $corpus->retrievalVersion,
            $mode,
            $historyHash,
            sha1(mb_strtolower($question)),
        ]);
    }

    private function rateLimit(Request $request): ?JsonResponse
    {
        [$attempts, $minutes] = $this->throttle();
        $user = $request->user();
        $identity = $user === null ? 'ip:'.$request->ip() : 'user:'.get_class($user).':'.(string) $user->getAuthIdentifier();
        $key = 'docent-ai:'.sha1($identity);

        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            $retry = RateLimiter::availableIn($key);

            return response()->json(['message' => 'Too many questions. Please try again shortly.'], 429, ['Retry-After' => (string) $retry]);
        }

        RateLimiter::hit($key, $minutes * 60);

        return null;
    }

    /** @return array{int, int} */
    private function throttle(): array
    {
        $parts = array_map('trim', explode(',', (string) $this->docent->config('ai.throttle', '10,1')));

        return [max(1, (int) $parts[0]), max(1, (int) ($parts[1] ?? 1))];
    }

    private function normalize(string $question): string
    {
        return trim(preg_replace('/\s+/', ' ', $question) ?? $question);
    }

    /** @return array<string, mixed> */
    private function citationsPayload(AiCorpus $corpus, ?AiQuestion $log): array
    {
        $payload = [
            'citations' => $corpus->citations,
            'question_id' => $log?->getKey(),
            'feedback_token' => $log?->feedbackToken(),
        ];

        if ($this->docent->config('ai.retrieval.debug', false)) {
            $payload['retrieval'] = $corpus->diagnostics;
        }

        return $payload;
    }

    /** @param callable(): void $callback */
    private function eventStream(callable $callback): StreamedResponse
    {
        return response()->stream($callback, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function emit(string $event, array $data): void
    {
        echo 'event: '.$event."\n";
        echo 'data: '.json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
