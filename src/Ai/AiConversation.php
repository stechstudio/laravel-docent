<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

final readonly class AiConversation
{
    public const SCHEMA = 1;

    /** @param list<AiConversationTurn> $turns */
    public function __construct(
        public string $id,
        public string $mode,
        public string $ownerHash,
        public string $viewerFingerprint,
        public string $corpusVersion,
        public array $turns,
        public int $turnCount,
        public int $createdAt,
        public int $updatedAt,
        public int $expiresAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA,
            'id' => $this->id,
            'mode' => $this->mode,
            'owner_hash' => $this->ownerHash,
            'viewer_fingerprint' => $this->viewerFingerprint,
            'corpus_version' => $this->corpusVersion,
            'turns' => array_map(static fn (AiConversationTurn $turn): array => $turn->toArray(), $this->turns),
            'turn_count' => $this->turnCount,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'expires_at' => $this->expiresAt,
        ];
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): ?self
    {
        if (($value['schema'] ?? null) !== self::SCHEMA || ! is_array($value['turns'] ?? null)) {
            return null;
        }

        return new self(
            (string) ($value['id'] ?? ''),
            (string) ($value['mode'] ?? ''),
            (string) ($value['owner_hash'] ?? ''),
            (string) ($value['viewer_fingerprint'] ?? ''),
            (string) ($value['corpus_version'] ?? ''),
            array_values(array_map(
                static fn (array $turn): AiConversationTurn => AiConversationTurn::fromArray($turn),
                array_filter($value['turns'], 'is_array'),
            )),
            (int) ($value['turn_count'] ?? 0),
            (int) ($value['created_at'] ?? 0),
            (int) ($value['updated_at'] ?? 0),
            (int) ($value['expires_at'] ?? 0),
        );
    }

    public function withoutLastTurn(): self
    {
        $turns = $this->turns;
        array_pop($turns);

        return new self(
            $this->id,
            $this->mode,
            $this->ownerHash,
            $this->viewerFingerprint,
            $this->corpusVersion,
            $turns,
            max(0, $this->turnCount - 1),
            $this->createdAt,
            $this->updatedAt,
            $this->expiresAt,
        );
    }

    public function withTurn(string $question, string $answer, int $ttl, int $maxTurns, int $historyBudget): self
    {
        $now = time();
        $turns = [...$this->turns, new AiConversationTurn($question, $answer, $now)];

        while (count($turns) > max(1, $maxTurns) || $this->tokens($turns) > max(1, $historyBudget)) {
            array_shift($turns);
        }

        return new self(
            $this->id,
            $this->mode,
            $this->ownerHash,
            $this->viewerFingerprint,
            $this->corpusVersion,
            $turns,
            $this->turnCount + 1,
            $this->createdAt,
            $now,
            $now + max(1, $ttl),
        );
    }

    /** @param list<AiConversationTurn> $turns */
    private function tokens(array $turns): int
    {
        return array_sum(array_map(static fn (AiConversationTurn $turn): int => $turn->estimatedTokens(), $turns));
    }
}
