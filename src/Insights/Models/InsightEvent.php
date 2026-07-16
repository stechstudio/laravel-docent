<?php

declare(strict_types=1);

namespace STS\Docent\Insights\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A deliberately narrow, identity-free documentation usage signal.
 *
 * @property int $id
 * @property string $event_id
 * @property string $category
 * @property string $event
 * @property string $surface
 * @property string|null $page_slug
 * @property string|null $query
 * @property string|null $search_id
 * @property string|null $reference_id
 * @property string|null $target_slug
 * @property int|null $result_count
 * @property list<string>|null $result_slugs
 * @property string|null $status
 * @property list<string>|null $citations
 * @property string|null $feedback
 * @property CarbonImmutable $created_at
 */
final class InsightEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'docent_insight_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'result_slugs' => 'array',
            'citations' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
