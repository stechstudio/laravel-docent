<?php

declare(strict_types=1);

namespace STS\Docent\Ai\Models;

use Illuminate\Database\Eloquent\Model;

/** A logged question and its lightweight quality signal. Answers are never stored. */
final class AiQuestion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'docent_ai_questions';

    protected $guarded = [];

    /** Rows have no viewer binding, so feedback authorization rides this token. */
    public function feedbackToken(): string
    {
        return hash_hmac('sha256', 'docent-ai-feedback:'.$this->getKey(), (string) config('app.key'));
    }
}
