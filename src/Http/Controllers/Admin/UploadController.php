<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Stores an uploaded image on the configured admin disk under `docent/`, with a
 * hashed filename, and returns its URL and path for embedding.
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
            'url' => Storage::disk($disk)->url($path),
            'path' => $path,
        ], 201);
    }
}
