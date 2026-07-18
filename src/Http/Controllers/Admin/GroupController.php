<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Docent\Admin\Editor;
use STS\Docent\Support\Icon;

/**
 * Manage sidebar group metadata (label/order/icon) for directories that hold
 * pages. Writes land as reserved `_groups/{directory}` rows overriding any
 * `_group.yml`, taking effect immediately with no publish step. Thin: the work
 * lives in {@see Editor}.
 */
final class GroupController
{
    public function index(Editor $editor): JsonResponse
    {
        return response()->json(['groups' => $editor->adminGroups()]);
    }

    public function update(Request $request, string $directory, Editor $editor): JsonResponse
    {
        $this->assertManageable($directory, $editor);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'order' => ['nullable', 'integer'],
            'icon' => ['nullable', 'string', static function (string $attribute, mixed $value, callable $fail): void {
                if (is_string($value) && $value !== '' && ! Icon::has($value)) {
                    $fail('Unknown icon "'.$value.'".');
                }
            }],
        ]);

        $meta = ['label' => $validated['label']];

        if (($validated['order'] ?? null) !== null) {
            $meta['order'] = (int) $validated['order'];
        }

        if (($validated['icon'] ?? null) !== null && $validated['icon'] !== '') {
            $meta['icon'] = $validated['icon'];
        }

        $editor->updateGroupMeta($directory, $meta, $this->authorId($request));

        return response()->json(['groups' => $editor->adminGroups()]);
    }

    public function destroy(string $directory, Editor $editor): JsonResponse
    {
        $this->assertManageable($directory, $editor);

        abort_unless($editor->removeGroupMeta($directory), 404);

        return response()->json(['groups' => $editor->adminGroups()]);
    }

    /**
     * A directory is manageable only when it is a well-formed slug path AND
     * currently holds pages — otherwise a 404, mirroring the page routes.
     */
    private function assertManageable(string $directory, Editor $editor): void
    {
        if (preg_match('#^[a-z0-9]([a-z0-9/-]*[a-z0-9])?$#', $directory) !== 1) {
            abort(404);
        }

        foreach ($editor->adminGroups() as $group) {
            if ($group['directory'] === $directory) {
                return;
            }
        }

        abort(404);
    }

    private function authorId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return $id === null ? null : (int) $id;
    }
}
