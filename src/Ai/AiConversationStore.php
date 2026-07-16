<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use STS\Docent\Ai\Conversation\AiConversationBusy;
use STS\Docent\Ai\Conversation\AiConversationExpired;
use STS\Docent\Ai\Conversation\AiConversationForbidden;
use STS\Docent\Ai\Conversation\AiConversationResolution;
use STS\Docent\DocentManager;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Support\DocentCache;

final class AiConversationStore
{
    public function __construct(private readonly DocentCache $cache) {}

    public function resolve(
        Request $request,
        DocumentationContext $context,
        string $corpusVersion,
        string $mode,
        ?string $id,
        ?string $token,
    ): AiConversationResolution {
        $owner = $this->ownerHash($request, $context, $mode);

        if ($id === null && $token === null) {
            return $this->create($owner, $context, $corpusVersion, $mode);
        }

        if ($id === null || $token === null || ! hash_equals($this->token($id, $owner, $mode), $token)) {
            throw new AiConversationForbidden('This conversation is not available.');
        }

        $conversation = $this->find($id);

        if ($conversation === null || $conversation->expiresAt <= time()) {
            $this->cache->forget($this->key($id));
            throw new AiConversationExpired('This help session has expired. Start a new conversation.');
        }

        if (! hash_equals($conversation->ownerHash, $owner) || $conversation->mode !== $mode) {
            throw new AiConversationForbidden('This conversation is not available.');
        }

        $viewer = $this->viewerFingerprint($context);

        if (! hash_equals($conversation->viewerFingerprint, $viewer) || ! hash_equals($conversation->corpusVersion, $corpusVersion)) {
            $this->cache->forget($this->key($id));

            return $this->create($owner, $context, $corpusVersion, $mode, 'viewer_or_corpus_changed');
        }

        return new AiConversationResolution($conversation, $token);
    }

    public function save(AiConversation $conversation): void
    {
        $this->cache->put($this->key($conversation->id), $conversation->toArray(), $this->ttl());
    }

    public function forget(AiConversation $conversation): void
    {
        $this->cache->forget($this->key($conversation->id));
    }

    public function acquire(AiConversation $conversation): void
    {
        if (! $this->cache->add($this->lockKey($conversation->id), ['locked' => true], 120)) {
            throw new AiConversationBusy('The Assistant is already answering in this conversation.');
        }
    }

    public function release(AiConversation $conversation): void
    {
        $this->cache->forget($this->lockKey($conversation->id));
    }

    public function tokenFor(AiConversation $conversation): string
    {
        return $this->token($conversation->id, $conversation->ownerHash, $conversation->mode);
    }

    private function create(
        string $owner,
        DocumentationContext $context,
        string $corpusVersion,
        string $mode,
        ?string $resetReason = null,
    ): AiConversationResolution {
        $now = time();
        $conversation = new AiConversation(
            (string) Str::uuid(),
            $mode,
            $owner,
            $this->viewerFingerprint($context),
            $corpusVersion,
            [],
            0,
            $now,
            $now,
            $now + $this->ttl(),
        );
        $this->save($conversation);

        return new AiConversationResolution(
            $conversation,
            $this->tokenFor($conversation),
            $resetReason,
        );
    }

    private function find(string $id): ?AiConversation
    {
        $value = $this->cache->get($this->key($id));

        return is_array($value) ? AiConversation::fromArray($value) : null;
    }

    private function ownerHash(Request $request, DocumentationContext $context, string $mode): string
    {
        $user = $context->user;
        if ($user === null) {
            $nonce = 'no-session';

            if ($request->hasSession()) {
                $nonce = $request->session()->get('docent.ai_conversation_owner');

                if (! is_string($nonce) || $nonce === '') {
                    $nonce = Str::random(32);
                    $request->session()->put('docent.ai_conversation_owner', $nonce);
                }
            }

            $identity = 'guest:'.$nonce;
        } else {
            $identity = 'user:'.get_class($user).':'.(string) $user->getAuthIdentifier();
        }

        return hash_hmac('sha256', $identity.'|'.$mode, $this->keyMaterial());
    }

    private function viewerFingerprint(DocumentationContext $context): string
    {
        $manager = app(DocentManager::class);

        return $manager->viewerFingerprint($context);
    }

    private function token(string $id, string $owner, string $mode): string
    {
        return hash_hmac('sha256', $id.'|'.$owner.'|'.$mode, $this->keyMaterial());
    }

    private function keyMaterial(): string
    {
        $key = (string) config('app.key', 'docent');

        return $key === '' ? 'docent' : $key;
    }

    private function ttl(): int
    {
        return max(1, (int) config('docent.ai.conversation.ttl', 7200));
    }

    private function key(string $id): string
    {
        return 'ai-conversation:'.hash('sha256', $id);
    }

    private function lockKey(string $id): string
    {
        return 'ai-conversation-lock:'.hash('sha256', $id);
    }
}
