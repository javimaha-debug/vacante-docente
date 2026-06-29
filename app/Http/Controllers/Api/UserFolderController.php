<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserFolderController extends Controller
{
    /** Folders as a tree (root folders with nested children), with doc counts. */
    public function index(Request $request): JsonResponse
    {
        $folders = UserFolder::query()
            ->where('user_id', $request->user()->id)
            ->withCount('documents')
            ->orderBy('position')->orderBy('name')
            ->get();

        $byParent = $folders->groupBy('parent_id');
        $build = function ($parentId) use (&$build, $byParent) {
            return ($byParent->get($parentId) ?? collect())->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'color' => $f->color,
                'tema_id' => $f->tema_id,
                'position' => $f->position,
                'documents_count' => $f->documents_count,
                'children' => $build($f->id),
            ])->values();
        };

        return response()->json(['data' => $build(null)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:9'],
            'parent_id' => ['nullable', 'integer', 'exists:user_folders,id'],
            'tema_id' => ['nullable', 'integer'],
            'position' => ['nullable', 'integer'],
        ]);

        if (! empty($data['parent_id'])) {
            $this->assertOwns($request, (int) $data['parent_id']);
        }

        $folder = UserFolder::create($data + ['user_id' => $request->user()->id]);

        return response()->json($folder, 201);
    }

    public function update(Request $request, UserFolder $folder): JsonResponse
    {
        $this->assertOwnsFolder($request, $folder);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'max:9'],
            'tema_id' => ['sometimes', 'nullable', 'integer'],
            'position' => ['sometimes', 'integer'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:user_folders,id'],
        ]);

        // Prevent making a folder its own parent.
        if (array_key_exists('parent_id', $data) && (int) $data['parent_id'] === $folder->id) {
            unset($data['parent_id']);
        }

        $folder->fill($data)->save();

        return response()->json($folder->fresh());
    }

    /** Delete a folder; its documents and subfolders move to the root first. */
    public function destroy(Request $request, UserFolder $folder): JsonResponse
    {
        $this->assertOwnsFolder($request, $folder);

        $folder->documents()->update(['folder_id' => null]);
        $folder->children()->update(['parent_id' => null]);
        $folder->delete();

        return response()->json(['deleted' => true]);
    }

    private function assertOwnsFolder(Request $request, UserFolder $folder): void
    {
        abort_unless($folder->user_id === $request->user()->id, 403);
    }

    private function assertOwns(Request $request, int $folderId): void
    {
        abort_unless(
            UserFolder::where('id', $folderId)->where('user_id', $request->user()->id)->exists(),
            403,
        );
    }
}
