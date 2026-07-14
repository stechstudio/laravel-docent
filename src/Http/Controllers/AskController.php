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
use STS\Docent\Ai\AiCorpus;
use STS\Docent\Ai\AiCorpusBuilder;
use STS\Docent\Ai\AiQuestionLogger;
use STS\Docent\Ai\Models\AiQuestion;
use STS\Docent\DocentManager;
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
    ) {}

    public function __invoke(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        if (($limited = $this->rateLimit($request)) !== null) {
            return $limited;
        }

        $question = $this->normalize((string) $validated['question']);
        $widget = $request->string('mode')->toString() === 'widget' && config('docent.widget.enabled', false);

        if ($widget) {
            $this->docent->enableWidgetMode();
        }

        $context = $this->docent->contextFor($request);
        $corpus = $this->corpus->build($context, $widget);
        $log = $this->questions->start($question, $context);
        $cacheKey = 'ai-answer:'.$corpus->version.':'.sha1(mb_strtolower($question));
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached) && is_string($cached['answer'] ?? null)) {
            $this->questions->finish($log, $cached['answer']);

            return $this->cachedResponse($corpus, $log, $cached['answer']);
        }

        return $this->liveResponse($corpus, $question, $cacheKey, $log);
    }

    private function rateLimit(Request $request): ?JsonResponse
    {
        [$attempts, $minutes] = $this->throttle();
        $user = $request->user();
        $identity = $user === null
            ? 'ip:'.$request->ip()
            : 'user:'.get_class($user).':'.(string) $user->getAuthIdentifier();
        $key = 'docent-ai:'.sha1($identity);

        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            $retry = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many questions. Please try again shortly.',
            ], 429, ['Retry-After' => (string) $retry]);
        }

        RateLimiter::hit($key, $minutes * 60);

        return null;
    }

    /** @return array{int, int} */
    private function throttle(): array
    {
        $parts = array_map('trim', explode(',', (string) config('docent.ai.throttle', '10,1')));

        return [max(1, (int) ($parts[0] ?? 10)), max(1, (int) ($parts[1] ?? 1))];
    }

    private function normalize(string $question): string
    {
        return trim(preg_replace('/\s+/', ' ', $question) ?? $question);
    }

    private function cachedResponse(AiCorpus $corpus, ?AiQuestion $log, string $answer): StreamedResponse
    {
        return $this->eventStream(function () use ($corpus, $log, $answer): void {
            $this->emit('citations', $this->citationsPayload($corpus, $log));

            if ($answer !== '') {
                $this->emit('text_delta', ['delta' => $answer]);
                $this->emit('answer_rendered', [
                    'html' => $this->renderer->render($answer, $corpus->citations),
                ]);
            }

            $this->emit('stream_end', ['finish_reason' => 'Stop', 'cached' => true]);
        });
    }

    private function liveResponse(AiCorpus $corpus, string $question, string $cacheKey, ?AiQuestion $log): StreamedResponse
    {
        return $this->eventStream(function () use ($corpus, $question, $cacheKey, $log): void {
            $this->emit('citations', $this->citationsPayload($corpus, $log));
            $answer = '';
            $failed = false;
            $streamEnd = ['finish_reason' => 'Stop'];

            try {
                foreach ($this->answers->stream($corpus, $question) as $event) {
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

            // A failed or empty generation must never be replayed as an answer.
            if (! $failed && $answer !== '') {
                $this->cache->put(
                    $cacheKey,
                    ['answer' => $answer],
                    max(1, (int) config('docent.ai.answer_ttl', 300)),
                );

                $this->emit('answer_rendered', [
                    'html' => $this->renderer->render($answer, $corpus->citations),
                ]);
            }

            $this->emit('stream_end', $streamEnd);
        });
    }

    /** @return array<string, mixed> */
    private function citationsPayload(AiCorpus $corpus, ?AiQuestion $log): array
    {
        return [
            'citations' => $corpus->citations,
            'question_id' => $log?->getKey(),
            'feedback_token' => $log?->feedbackToken(),
        ];
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
