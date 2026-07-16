<?php

declare(strict_types=1);

namespace STS\Docent\Insights\Models;

use Illuminate\Database\Eloquent\Model;

/** A deliberately narrow, identity-free documentation usage signal. */
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
