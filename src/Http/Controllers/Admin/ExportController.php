<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use STS\Docent\Admin\Editor;
use STS\Docent\Documents\Serializer\MarkdownExporter;
use STS\Docent\Http\Controllers\Admin\Concerns\InteractsWithPages;

/**
 * Exports any page — file or database, markdown or Tiptap — to normalized
 * markdown (with a front matter block) via the AST → {@see MarkdownExporter}
 * pivot. Powers the admin "View markdown" action.
 */
final class ExportController
{
    use InteractsWithPages;

    public function __invoke(string $slug, Editor $editor): JsonResponse
    {
        $slug = $this->resolveSlug($slug);

        return response()->json(['markdown' => $editor->exportMarkdown($slug) ?? abort(404)]);
    }
}
