<?php

declare(strict_types=1);

namespace STS\Docent\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A database-authored documentation page and its revision history. This model is
 * the write API for the database store: {@see write()} upserts a page and
 * snapshots a revision, {@see publish()} points the reader pipeline at a chosen
 * revision, and drafts stay invisible until published.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $content
 * @property string $format
 * @property array<string, mixed>|null $front_matter
 * @property int|null $published_revision_id
 * @property int|null $created_by
 * @property int|null $updated_by
 */
final class DocentPage extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'front_matter' => 'array',
    ];

    /**
     * Upsert a page by slug and snapshot a revision whenever the content,
     * format, or front matter actually changed. The title comes from the front
     * matter `title`, falling back to a headline of the slug's last segment.
     *
     * @param  array<string, mixed>  $frontMatter
     */
    public static function write(string $slug, string $content, array $frontMatter = [], ?int $authorId = null, string $format = 'markdown'): self
    {
        $page = self::withTrashed()->firstOrNew(['slug' => $slug]);

        $changed = ! $page->exists
            || $page->trashed()
            || $page->content !== $content
            || $page->format !== $format
            || $page->front_matter !== ($frontMatter ?: null);

        $page->fill([
            'title' => self::titleFor($slug, $frontMatter),
            'content' => $content,
            'format' => $format,
            'front_matter' => $frontMatter ?: null,
            'updated_by' => $authorId,
            'deleted_at' => null,
        ]);

        if (! $page->exists) {
            $page->created_by = $authorId;
        }

        $page->save();

        if ($changed) {
            $page->revisions()->create([
                'content' => $content,
                'format' => $format,
                'front_matter' => $frontMatter ?: null,
                'created_by' => $authorId,
            ]);

            $page->unsetRelation('revisions');
        }

        return $page;
    }

    /**
     * Point the published pointer at the given revision (the latest by default),
     * making its content the one the reader pipeline serves.
     */
    public function publish(?DocentPageRevision $revision = null): self
    {
        $revision ??= $this->latestRevision();

        $this->published_revision_id = $revision?->getKey();
        $this->save();

        return $this;
    }

    public function unpublish(): self
    {
        $this->published_revision_id = null;
        $this->save();

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published_revision_id !== null;
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->latestRevision()?->getKey() !== $this->published_revision_id;
    }

    /**
     * Re-apply a past revision's content as a new revision, preserving history.
     */
    public function revertTo(DocentPageRevision $revision): self
    {
        return self::write($this->slug, $revision->content, $revision->front_matter ?? [], $revision->created_by, $revision->format);
    }

    public function latestRevision(): ?DocentPageRevision
    {
        return $this->revisions()->latest('id')->first();
    }

    /**
     * @return HasMany<DocentPageRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(DocentPageRevision::class);
    }

    /**
     * @return BelongsTo<DocentPageRevision, $this>
     */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(DocentPageRevision::class, 'published_revision_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_revision_id');
    }

    /**
     * @param  array<string, mixed>  $frontMatter
     */
    private static function titleFor(string $slug, array $frontMatter): string
    {
        $title = $frontMatter['title'] ?? null;

        return is_scalar($title) && $title !== '' ? (string) $title : Str::headline(Str::afterLast($slug, '/'));
    }
}
