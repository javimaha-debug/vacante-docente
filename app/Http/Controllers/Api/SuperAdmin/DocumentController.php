<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DetectedDocument;
use App\Models\MonitoredSource;
use App\Services\DocumentMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /** List detected documents, newest first, with optional filters. */
    public function index(Request $request): JsonResponse
    {
        $docs = DetectedDocument::query()
            ->with(['source:id,name,type', 'validator:id,name'])
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('document_type', $v))
            ->when($request->query('source'), fn ($q, $v) => $q->where('source_id', $v))
            ->orderByDesc('detected_at')
            ->orderByDesc('id')
            ->paginate(30);

        return response()->json($docs);
    }

    public function show(DetectedDocument $document): JsonResponse
    {
        return response()->json(
            $document->load(['source:id,name,type', 'validator:id,name', 'calendarEvents'])
        );
    }

    /** Mark a document as validated (reviewed and correct). */
    public function validateDoc(Request $request, DetectedDocument $document): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        $document->forceFill([
            'status' => 'validated',
            'superadmin_notes' => $data['notes'] ?? $document->superadmin_notes,
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
        ])->save();

        return response()->json($document->fresh(['source', 'validator']));
    }

    public function reject(Request $request, DetectedDocument $document): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        $document->forceFill([
            'status' => 'rejected',
            'superadmin_notes' => $data['notes'] ?? $document->superadmin_notes,
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
        ])->save();

        return response()->json($document->fresh(['source', 'validator']));
    }

    /** Publish a document to users, optionally confirming its calendar event. */
    public function publish(Request $request, DetectedDocument $document): JsonResponse
    {
        $data = $request->validate([
            'confirm_event' => ['sometimes', 'boolean'],
            'event_visibility' => ['sometimes', 'in:public,users_only'],
        ]);

        $document->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'validated_by' => $document->validated_by ?? $request->user()->id,
            'validated_at' => $document->validated_at ?? now(),
        ])->save();

        if (! empty($data['confirm_event'])) {
            $document->calendarEvents()->update([
                'is_confirmed' => true,
                'is_estimated' => false,
                'visibility' => $data['event_visibility'] ?? 'public',
            ]);
        }

        return response()->json($document->fresh(['source', 'validator', 'calendarEvents']));
    }

    /** Manual PDF upload by a superadmin (already considered validated). */
    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'in:listado_provisional,listado_definitivo,vacantes,resolucion,convocatoria,otro'],
            'source_id' => ['nullable', 'integer', 'exists:monitored_sources,id'],
            'title' => ['required', 'string', 'max:480'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:51200'],
            'publish_now' => ['sometimes', 'boolean'],
        ]);

        $path = $request->file('pdf')->store('documents', 'local');
        $publishNow = ! empty($data['publish_now']);

        $document = DetectedDocument::create([
            'source_id' => $data['source_id'] ?? null,
            'title' => $data['title'],
            'detected_at' => now(),
            'document_type' => $data['document_type'],
            'status' => $publishNow ? 'published' : 'validated',
            'pdf_path' => $path,
            'superadmin_notes' => $data['notes'] ?? null,
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
            'published_at' => $publishNow ? now() : null,
        ]);

        return response()->json($document->load('source'), 201);
    }

    /** Counts by status and by type, for the dashboard badges/cards. */
    public function stats(): JsonResponse
    {
        return response()->json([
            'por_estado' => DetectedDocument::query()
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'por_tipo' => DetectedDocument::query()
                ->selectRaw('document_type, count(*) as total')->groupBy('document_type')->pluck('total', 'document_type'),
            'pendientes' => DetectedDocument::where('status', 'pending')->count(),
        ]);
    }

    /** List monitored sources with their document counts. */
    public function sources(): JsonResponse
    {
        $sources = MonitoredSource::query()
            ->withCount('documents')
            ->orderBy('type')->orderBy('name')
            ->get();

        return response()->json(['data' => $sources]);
    }

    /** Toggle a source active flag and/or update its url. */
    public function updateSource(Request $request, MonitoredSource $source): JsonResponse
    {
        $data = $request->validate([
            'active' => ['sometimes', 'boolean'],
            'url' => ['sometimes', 'url', 'max:500'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $source->fill($data)->save();

        return response()->json($source->fresh()->loadCount('documents'));
    }

    /** Run the monitor for a single source now ("Comprobar ahora"). */
    public function checkSource(MonitoredSource $source, DocumentMonitorService $service): JsonResponse
    {
        $result = $service->scan($source);

        return response()->json([
            'ok' => $result['error'] === null,
            'resumen' => $result,
            'source' => $source->fresh()->loadCount('documents'),
        ]);
    }

    /** Stream/redirect to a stored PDF (or its source url). */
    public function downloadPdf(DetectedDocument $document)
    {
        if ($document->pdf_path && Storage::disk('local')->exists($document->pdf_path)) {
            return Storage::disk('local')->download($document->pdf_path);
        }
        if ($document->pdf_url) {
            return redirect()->away($document->pdf_url);
        }

        return response()->json(['message' => 'No hay PDF disponible.'], 404);
    }
}
