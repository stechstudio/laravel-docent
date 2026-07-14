<?php

declare(strict_types=1);

namespace STS\Docent\Ai\Conversation;

use STS\Docent\Ai\AiConversation;

final readonly class AiConversationResolution
{
    public function __construct(
        public AiConversation $conversation,
        public string $token,
        public ?string $resetReason = null,
    ) {}
}
