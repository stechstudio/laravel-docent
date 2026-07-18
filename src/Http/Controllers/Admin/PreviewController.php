<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use STS\Docent\Admin\Editor;
use STS\Docent\DocentManager;
use STS\Docent\Http\Controllers\Admin\Concerns\InteractsWithPages;

/**
 * Renders an unsaved draft through the real pipeline with the admin's own
 * context, returning HTML, a table of contents, and the inline reference
 * checks. Accepts a markdown body (`content`) or a Tiptap document
 * (`content_tiptap`), exactly like the page write. No persistence.
 */
final class PreviewController
{
    use InteractsWithPages;

    public function __invoke(Request $request, DocentManager $docent, Editor $editor): JsonResponse
    {
        $request->validate(['front_matter' => ['array']]);
        $frontMatter = $request->input('front_matter', []);

        if ($request->has('content_tiptap')) {
            $request->validate(['content_tiptap' => ['array']]);
            // Raw body, not input — TrimStrings would eat rich-text whitespace.
            $tiptap = $this->rawTiptap($request);

            if ($tiptap === null || ($error = $editor->tiptapError($tiptap)) !== null) {
                throw ValidationException::withMessages(['content_tiptap' => $error ?? 'Invalid document.']);
            }

            $document = $editor->draftDocument('tiptap', json_encode($tiptap, JSON_THROW_ON_ERROR), $frontMatter);
        } else {
            $request->validate(['content' => ['present', 'string']]);
            $document = $editor->draftDocument('markdown', $request->string('content')->toString(), $frontMatter);
        }

        return response()->json($editor->previewDraft($document, $docent->contextFor($request)));
    }
}
