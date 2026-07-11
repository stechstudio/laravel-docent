<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use STS\Docent\Content\Models\DocentPage;
use STS\Docent\DocentManager;
use STS\Docent\Http\Controllers\Admin\Concerns\InteractsWithPages;

/**
 * CRUD for database-authored pages. Thin: page writes go through
 * {@see DocentPage::write()} (which snapshots a revision), and the editor
 * payload plus inline reference checks come from {@see DocentManager}.
 */
final class PageController
{
    use InteractsWithPages;

    /**
     * Front matter keys the admin accepts. Anything else is rejected so a typo
     * can never silently persist as meaningless metadata.
     */
    private const FRONT_MATTER_KEYS = [
        'title', 'description', 'authorize', 'audience', 'order', 'hidden', 'layout', 'hero', 'search',
    ];

    public function store(Request $request, DocentManager $docent): JsonResponse
    {
        $slug = $request->string('slug')->toString();
        $this->assertValidSlug($slug);

        if ($this->pageQuery()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'A database page with this slug already exists.',
            ]);
        }

        [$content, $frontMatter] = $this->payload($request);

        DocentPage::write($slug, $content, $frontMatter, $this->authorId($request));

        return $this->detailResponse($docent, $slug, $content, $frontMatter, 201);
    }

    public function show(string $slug, DocentManager $docent): JsonResponse
    {
        $this->guardTraversal($slug);

        return response()->json($docent->adminDetail($slug) ?? abort(404));
    }

    public function update(Request $request, string $slug, DocentManager $docent): JsonResponse
    {
        $this->guardTraversal($slug);
        $this->assertValidSlug($slug);

        [$content, $frontMatter] = $this->payload($request);

        DocentPage::write($slug, $content, $frontMatter, $this->authorId($request));

        return $this->detailResponse($docent, $slug, $content, $frontMatter);
    }

    public function destroy(string $slug): JsonResponse
    {
        $this->guardTraversal($slug);
        $this->findPageOrFail($slug)->delete();

        return response()->json(['deleted' => true]);
    }

    public function revisions(string $slug): JsonResponse
    {
        $this->guardTraversal($slug);

        $revisions = $this->findPageOrFail($slug)
            ->revisions()
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn ($revision): array => [
                'id' => $revision->id,
                'excerpt' => Str::limit($revision->content, 120),
                'created_at' => $revision->created_at,
                'created_by' => $revision->created_by,
            ]);

        return response()->json(['revisions' => $revisions]);
    }

    /**
     * Validate and normalize a page write: title (required) folds into the front
     * matter, and every front matter key must be known.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function payload(Request $request): array
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['present', 'string'],
            'front_matter' => ['array'],
        ]);

        $frontMatter = $request->input('front_matter', []);
        $unknown = array_diff(array_keys($frontMatter), self::FRONT_MATTER_KEYS);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'front_matter' => 'Unknown front matter keys: '.implode(', ', $unknown).'.',
            ]);
        }

        $frontMatter['title'] = $request->string('title')->toString();

        return [$request->string('content')->toString(), $frontMatter];
    }

    /**
     * @param  array<string, mixed>  $frontMatter
     */
    private function detailResponse(DocentManager $docent, string $slug, string $content, array $frontMatter, int $status = 200): JsonResponse
    {
        return response()->json([
            ...$docent->adminDetail($slug),
            'issues' => $docent->draftIssues($slug, $content, $frontMatter),
        ], $status);
    }

    private function authorId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return $id === null ? null : (int) $id;
    }
}
