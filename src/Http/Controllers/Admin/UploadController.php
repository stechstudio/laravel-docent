<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use STS\Docent\DocentManager;

/**
 * Stores an uploaded image on the configured admin disk under the site's
 * `docent/{site}/` namespace, with a hashed filename, and returns its URL and
 * path for embedding. The URL points
 * at the docs `_uploads` streaming route, not the disk — uploads work on any
 * disk (public, local, private S3) with no storage:link or bucket policy.
 * SVGs are sanitized before storage: the stored bytes must be inert no matter
 * how a host ends up serving them (a `storage:link` symlink bypasses the
 * streaming route's protective headers entirely).
 */
final class UploadController
{
    public function __construct(
        private readonly DocentManager $docent,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,gif,svg,webp', 'max:5120'],
        ]);

        $file = $request->file('file');
        $disk = (string) $this->docent->config('admin.disk', 'public');

        // Uploads are namespaced per site so sites sharing a disk can never
        // stream each other's images through their own `_uploads` route.
        $directory = 'docent/'.$this->docent->key();

        if ($file->guessExtension() === 'svg') {
            $path = $directory.'/'.$file->hashName();
            Storage::disk($disk)->put($path, $this->sanitizedSvg($file));
        } else {
            $path = $file->store($directory, ['disk' => $disk]);
        }

        return response()->json([
            'url' => $this->docent->route('upload', ['path' => $path]),
            'path' => $path,
        ], 201);
    }

    private function sanitizedSvg(UploadedFile $file): string
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);

        $clean = $sanitizer->sanitize((string) $file->get());

        if ($clean === false || trim($clean) === '') {
            throw ValidationException::withMessages([
                'file' => 'The SVG could not be sanitized for safe display.',
            ]);
        }

        return $clean;
    }
}
