<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

final readonly class AiConversationTurn
{
    public function __construct(
        public string $question,
        public string $answer,
        public int $createdAt,
    ) {}

    /** @return array{question: string, answer: string, created_at: int} */
    public function toArray(): array
    {
        return [
            'question' => $this->question,
            'answer' => $this->answer,
            'created_at' => $this->createdAt,
        ];
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        return new self(
            (string) ($value['question'] ?? ''),
            (string) ($value['answer'] ?? ''),
            (int) ($value['created_at'] ?? time()),
        );
    }

    public function estimatedTokens(): int
    {
        return (int) ceil((strlen($this->question) + strlen($this->answer)) / 4);
    }
}
