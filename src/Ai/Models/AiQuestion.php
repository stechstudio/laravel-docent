<?php

declare(strict_types=1);

namespace STS\Docent\Ai\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A logged question and its lightweight quality signal. Answers are never stored.
 *
 * @property string $site
 */
final class AiQuestion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'docent_ai_questions';

    protected $guarded = [];

    protected $attributes = [
        'site' => 'docs',
    ];

    /** @return Builder<self> */
    public static function forSite(?string $connection, string $site): Builder
    {
        return self::on($connection)->where('site', $site);
    }

    /** Rows have no viewer binding, so feedback authorization rides this token. */
    public function feedbackToken(): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            // An empty HMAC key would make feedback tokens forgeable.
            throw new \RuntimeException('Docent AI feedback tokens require a configured application key (app.key).');
        }

        return hash_hmac('sha256', 'docent-ai-feedback:'.$this->site.':'.$this->getKey(), $key);
    }
}
