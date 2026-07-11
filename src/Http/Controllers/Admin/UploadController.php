<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stores an uploaded image on the configured admin disk under `docent/`, with a
 * hashed filename, and returns its URL and path for embedding. The URL points
 * at the docs `_uploads` streaming route, not the disk — uploads work on any
 * disk (public, local, private S3) with no storage:link or bucket policy.
 */
final class UploadController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,gif,svg,webp', 'max:5120'],
        ]);

        $disk = (string) config('docent.admin.disk', 'public');
        $path = $request->file('file')->store('docent', ['disk' => $disk]);

        return response()->json([
            'url' => route('docent.upload', ['path' => $path]),
            'path' => $path,
        ], 201);
    }
}
