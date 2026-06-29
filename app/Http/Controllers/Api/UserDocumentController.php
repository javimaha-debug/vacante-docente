<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDocumentJob;
use App\Models\UserDocument;
use App\Models\UserDocumentTag;
use App\Models\UserFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserDocumentController extends Controller
{
    private function disk(): string
    {
        return config('documents.disk');
    }

    /** Upload one or more files, store them and queue processing. */
    public function upload(Request $request): JsonResponse
    {
        $maxKb = (int) config('documents.max_kb');
        $exts = implode(',', config('documents.allowed_ext'));

        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', "max:{$maxKb}", "mimes:{$exts}"],
            'folder_id' => ['nullable', 'integer', 'exists:user_folders,id'],
            'tema_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $files = $request->file('files');

        // Quota check across the whole batch before storing anything.
        $batchBytes = array_sum(array_map(fn ($f) => $f->getSize(), $files));
        if ($user->exceedsStorage($batchBytes)) {
            return response()->json([
                'message' => 'No tienes espacio suficiente. Libera espacio o amplía tu plan.',
                'storage_used_bytes' => $user->storage_used_bytes,
                'storage_limit_bytes' => $user->storage_limit_bytes,
            ], 422);
        }

        $folderId = $request->input('folder_id');
        if ($folderId) {
            $this->assertOwnsFolder($request, (int) $folderId);
        }

        $created = [];
        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
            $path = "users/{$user->id}/docs/".Str::uuid()->toString().'.'.$ext;
            Storage::disk($this->disk())->put($path, file_get_contents($file->getRealPath()));

            $doc = UserDocument::create([
                'user_id' => $user->id,
                'folder_id' => $folderId,
                'name' => $file->getClientOriginalName(),
                'disk_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'type' => $this->typeFromExt($ext),
                'source' => 'upload',
                'processing_status' => 'pending',
                'tema_id' => $request->input('tema_id'),
            ]);

            $user->increment('storage_used_bytes', (int) $file->getSize());
            ProcessDocumentJob::dispatch($doc->id);
            $created[] = $this->present($doc);
        }

        return response()->json([
            'data' => $created,
            'storage_used_bytes' => $user->fresh()->storage_used_bytes,
            'storage_limit_bytes' => $user->storage_limit_bytes,
        ], 201);
    }

    /** List the user's documents with filters. */
    public function index(Request $request): JsonResponse
    {
        $docs = UserDocument::query()
            ->where('user_id', $request->user()->id)
            ->with(['folder:id,name,color', 'tags:id,name,color'])
            ->when($request->query('folder_id'), fn ($q, $v) => $q->where('folder_id', $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->query('tema_id'), fn ($q, $v) => $q->where('tema_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('processing_status', $v))
            ->when($request->boolean('root_only'), fn ($q) => $q->whereNull('folder_id'))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($d) => $this->present($d));

        return response()->json([
            'data' => $docs,
            'storage_used_bytes' => $request->user()->storage_used_bytes,
            'storage_limit_bytes' => $request->user()->storage_limit_bytes,
        ]);
    }

    public function show(Request $request, UserDocument $document): JsonResponse
    {
        $this->assertOwns($request, $document);
        $document->load(['folder:id,name,color', 'tags:id,name,color']);

        return response()->json($this->present($document, withViewUrl: true));
    }

    public function update(Request $request, UserDocument $document): JsonResponse
    {
        $this->assertOwns($request, $document);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:500'],
            'folder_id' => ['sometimes', 'nullable', 'integer', 'exists:user_folders,id'],
            'tema_id' => ['sometimes', 'nullable', 'integer'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'exists:user_document_tags,id'],
        ]);

        if (array_key_exists('folder_id', $data) && $data['folder_id']) {
            $this->assertOwnsFolder($request, (int) $data['folder_id']);
        }

        $document->fill(collect($data)->except('tag_ids')->all())->save();

        if (array_key_exists('tag_ids', $data)) {
            // Only attach tags the user owns.
            $owned = UserDocumentTag::where('user_id', $request->user()->id)->whereIn('id', $data['tag_ids'])->pluck('id');
            $document->tags()->sync($owned);
        }

        return response()->json($this->present($document->fresh(['folder', 'tags'])));
    }

    public function move(Request $request, UserDocument $document): JsonResponse
    {
        $this->assertOwns($request, $document);
        $data = $request->validate(['folder_id' => ['nullable', 'integer', 'exists:user_folders,id']]);

        if (! empty($data['folder_id'])) {
            $this->assertOwnsFolder($request, (int) $data['folder_id']);
        }

        $document->forceFill(['folder_id' => $data['folder_id'] ?? null])->save();

        return response()->json($this->present($document->fresh('folder')));
    }

    public function destroy(Request $request, UserDocument $document): JsonResponse
    {
        $this->assertOwns($request, $document);

        $disk = Storage::disk($this->disk());
        DB::transaction(function () use ($document, $disk, $request) {
            $disk->delete(array_filter([$document->disk_path, $document->thumbnail_path]));
            $request->user()->decrement('storage_used_bytes', min((int) $document->size_bytes, (int) $request->user()->storage_used_bytes));
            $document->delete();
        });

        return response()->json(['deleted' => true, 'storage_used_bytes' => $request->user()->fresh()->storage_used_bytes]);
    }

    /** Signed, short-lived streaming endpoint — never exposes the storage path. */
    public function view(Request $request, UserDocument $document): StreamedResponse
    {
        // Signature already validated by the 'signed' middleware. Defense in
        // depth: the link is bound to the owner's id (signed, so untamperable).
        abort_unless((int) $request->query('uid') === (int) $document->user_id, 403);

        $disk = Storage::disk($this->disk());
        abort_unless($disk->exists($document->disk_path), 404);

        return $disk->response($document->disk_path, $document->name, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
        ]);
    }

    /** Stream the stored thumbnail (signed). */
    public function thumbnail(Request $request, UserDocument $document): StreamedResponse
    {
        abort_unless((int) $request->query('uid') === (int) $document->user_id, 403);

        $disk = Storage::disk($this->disk());
        abort_unless($document->thumbnail_path && $disk->exists($document->thumbnail_path), 404);

        return $disk->response($document->thumbnail_path, 'thumb.jpg', ['Content-Type' => 'image/jpeg']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(UserDocument $d, bool $withViewUrl = false): array
    {
        $base = [
            'id' => $d->id,
            'name' => $d->name,
            'folder_id' => $d->folder_id,
            'folder' => $d->relationLoaded('folder') ? $d->folder : null,
            'type' => $d->type,
            'source' => $d->source,
            'mime_type' => $d->mime_type,
            'size_bytes' => $d->size_bytes,
            'processing_status' => $d->processing_status,
            'page_count' => $d->page_count,
            'word_count' => $d->word_count,
            'tema_id' => $d->tema_id,
            'notes' => $d->notes,
            'has_thumbnail' => (bool) $d->thumbnail_path,
            'thumbnail_url' => $d->thumbnail_path
                ? URL::temporarySignedRoute('documents.thumbnail', now()->addMinutes((int) config('documents.view_ttl_minutes')), ['document' => $d->id, 'uid' => $d->user_id])
                : null,
            'tags' => $d->relationLoaded('tags') ? $d->tags : [],
            'created_at' => $d->created_at,
            'updated_at' => $d->updated_at,
        ];

        if ($withViewUrl) {
            $base['view_url'] = URL::temporarySignedRoute(
                'documents.view',
                now()->addMinutes((int) config('documents.view_ttl_minutes')),
                ['document' => $d->id, 'uid' => $d->user_id],
            );
        }

        return $base;
    }

    private function typeFromExt(string $ext): string
    {
        return match ($ext) {
            'pdf' => 'pdf',
            'doc', 'docx' => 'word',
            'jpg', 'jpeg', 'png', 'webp' => 'image',
            default => 'other',
        };
    }

    private function assertOwns(Request $request, UserDocument $document): void
    {
        abort_unless($document->user_id === $request->user()->id, 403);
    }

    private function assertOwnsFolder(Request $request, int $folderId): void
    {
        abort_unless(
            UserFolder::where('id', $folderId)->where('user_id', $request->user()->id)->exists(),
            403,
        );
    }
}
