<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use STS\Docent\Admin\Editor;
use STS\Docent\Content\Models\DocentPage;
use STS\Docent\DocentManager;
use STS\Docent\Documents\Parser\TiptapDocumentParser;
use STS\Docent\Http\Controllers\Admin\Concerns\InteractsWithPages;

/**
 * CRUD for database-authored pages. Thin: page writes go through
 * {@see DocentPage::write()} (which snapshots a revision), and the editor
 * payload plus inline reference checks come from {@see Editor}.
 */
final class PageController
{
    use InteractsWithPages;

    /**
     * Front matter keys the admin accepts. Anything else is rejected so a typo
     * can never silently persist as meaningless metadata.
     */
    private const FRONT_MATTER_KEYS = [
        'title', 'description', 'authorize', 'audience', 'order', 'hidden', 'layout', 'hero', 'search', 'redirect',
    ];

    public function store(Request $request, DocentManager $docent, Editor $editor): JsonResponse
    {
        $slug = $request->string('slug')->toString();
        $this->assertValidSlug($slug);
        $this->assertUnlocked($slug, $editor);

        if ($this->pageQuery()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'A database page with this slug already exists.',
            ]);
        }

        [$content, $frontMatter, $format] = $this->payload($request, $editor);

        DocentPage::write(
            $slug,
            $content,
            $frontMatter,
            $this->authorId($request),
            $format,
            $docent->key(),
            $this->connection(),
        );

        return $this->detailResponse($editor, $slug, $content, $frontMatter, $format, 201);
    }

    public function show(string $slug, Editor $editor): JsonResponse
    {
        $slug = $this->resolveSlug($slug);

        return response()->json($editor->adminDetail($slug) ?? abort(404));
    }

    public function update(Request $request, string $slug, DocentManager $docent, Editor $editor): JsonResponse
    {
        $slug = $this->resolveSlug($slug);
        $this->assertValidSlug($slug);
        $this->assertUnlocked($slug, $editor);

        [$content, $frontMatter, $format] = $this->payload($request, $editor);

        DocentPage::write(
            $slug,
            $content,
            $frontMatter,
            $this->authorId($request),
            $format,
            $docent->key(),
            $this->connection(),
        );

        return $this->detailResponse($editor, $slug, $content, $frontMatter, $format);
    }

    public function destroy(string $slug, Editor $editor): JsonResponse
    {
        $slug = $this->resolveSlug($slug);
        $this->assertUnlocked($slug, $editor);
        $this->findPageOrFail($slug)->delete();

        return response()->json(['deleted' => true]);
    }

    public function revisions(string $slug): JsonResponse
    {
        $slug = $this->resolveSlug($slug);

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
     * matter, every front matter key must be known, and the body arrives as
     * EITHER `content` (a markdown string) OR `content_tiptap` (a ProseMirror
     * document, validated against {@see TiptapDocumentParser}
     * and stored as JSON with `format: 'tiptap'`).
     *
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function payload(Request $request, Editor $editor): array
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
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

        if ($request->has('content_tiptap')) {
            $request->validate(['content_tiptap' => ['array']]);
            // Read from the raw body, not input — TrimStrings would eat
            // meaningful whitespace inside rich-text nodes.
            $tiptap = $this->rawTiptap($request);

            if ($tiptap === null || ($error = $editor->tiptapError($tiptap)) !== null) {
                throw ValidationException::withMessages(['content_tiptap' => $error ?? 'Invalid document.']);
            }

            return [json_encode($tiptap, JSON_THROW_ON_ERROR), $frontMatter, 'tiptap'];
        }

        $request->validate(['content' => ['present', 'string']]);

        return [$request->string('content')->toString(), $frontMatter, 'markdown'];
    }

    /**
     * @param  array<string, mixed>  $frontMatter
     */
    private function detailResponse(Editor $editor, string $slug, string $content, array $frontMatter, string $format, int $status = 200): JsonResponse
    {
        return response()->json([
            ...$editor->adminDetail($slug),
            'issues' => $editor->draftIssues($slug, $editor->draftDocument($format, $content, $frontMatter)),
        ], $status);
    }

    private function authorId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return $id === null ? null : (int) $id;
    }
}
