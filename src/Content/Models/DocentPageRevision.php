<?php

declare(strict_types=1);

namespace STS\Docent\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An immutable content snapshot of a {@see DocentPage}. Revisions are never
 * updated — a new one is written on every content or front-matter change — so
 * only `created_at` is tracked.
 *
 * @property int $id
 * @property int $docent_page_id
 * @property string $content
 * @property string $format
 * @property array<string, mixed>|null $front_matter
 * @property int|null $created_by
 * @property Carbon|null $created_at
 */
class DocentPageRevision extends Model
{
    const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'front_matter' => 'array',
    ];

    /**
     * @return BelongsTo<DocentPage, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(DocentPage::class, 'docent_page_id');
    }
}
