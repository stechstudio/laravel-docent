<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use STS\Docent\Ai\AiConversationStore;
use STS\Docent\Ai\AiCorpusBuilder;
use STS\Docent\Ai\Conversation\AiConversationExpired;
use STS\Docent\Ai\Conversation\AiConversationForbidden;
use STS\Docent\DocentManager;

final class AskConversationController
{
    public function __construct(
        private readonly DocentManager $docent,
        private readonly AiCorpusBuilder $corpus,
        private readonly AiConversationStore $conversations,
    ) {}

    public function __invoke(Request $request): Response|JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'uuid'],
            'conversation_token' => ['required', 'string', 'size:64'],
        ]);
        $widget = $request->string('mode')->toString() === 'widget' && $this->docent->config('widget.enabled', false);

        if ($widget) {
            $this->docent->enableWidgetMode();
        }

        $context = $this->docent->contextFor($request);
        $corpusVersion = $this->corpus->version($context, $widget);

        try {
            $resolution = $this->conversations->resolve(
                $request,
                $context,
                $corpusVersion,
                $widget ? 'widget' : 'reader',
                (string) $validated['conversation_id'],
                (string) $validated['conversation_token'],
            );
        } catch (AiConversationForbidden $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (AiConversationExpired $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'conversation_expired'], 409);
        }

        $this->conversations->forget($resolution->conversation);

        return response()->noContent();
    }
}
