<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\NormativaDocumento;
use App\Models\SyncState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class NormativaController extends Controller
{
    /**
     * Full list for the admin table.
     */
    public function index(Request $request): JsonResponse
    {
        $docs = NormativaDocumento::query()
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $docs->map(fn ($d) => $this->adminArray($d))]);
    }

    /**
     * Last sync timestamps per automated source (BOE / DOGV).
     */
    public function syncState(): JsonResponse
    {
        $states = SyncState::whereIn('clave', ['normativa_boe', 'normativa_dogv'])
            ->get()->keyBy('clave');

        return response()->json([
            'boe' => $this->stateArray($states->get('normativa_boe')),
            'dogv' => $this->stateArray($states->get('normativa_dogv')),
        ]);
    }

    /**
     * Run the BOE normativa sync synchronously.
     */
    public function syncBoe(): JsonResponse
    {
        $exit = Artisan::call('normativa:sync-boe');

        return response()->json([
            'ran' => $exit === 0,
            'output' => trim(Artisan::output()),
            'state' => $this->stateArray(SyncState::where('clave', 'normativa_boe')->first()),
        ]);
    }

    /**
     * Run the DOGV normativa sync synchronously.
     */
    public function syncDogv(): JsonResponse
    {
        $exit = Artisan::call('normativa:sync-dogv');

        return response()->json([
            'ran' => $exit === 0,
            'output' => trim(Artisan::output()),
            'state' => $this->stateArray(SyncState::where('clave', 'normativa_dogv')->first()),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stateArray(?SyncState $state): ?array
    {
        if (! $state) {
            return null;
        }

        return [
            'last_run_at' => $state->last_run_at?->toIso8601String(),
            'resumen' => $state->resumen,
        ];
    }

    /**
     * Create a normativa document, optionally with a PDF upload.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request, true);

        if ($request->hasFile('pdf')) {
            $data['pdf_path'] = $request->file('pdf')->store('normativa', 'public');
        }

        $data['published_by'] = $request->user()->id;
        $data['published_at'] = now();

        $doc = NormativaDocumento::create($data);

        return response()->json($this->adminArray($doc), 201);
    }

    /**
     * Update a document (metadata, PDF, vigente).
     */
    public function update(Request $request, NormativaDocumento $normativa): JsonResponse
    {
        $data = $this->validatePayload($request, false);

        if ($request->hasFile('pdf')) {
            if ($normativa->pdf_path) {
                Storage::disk('public')->delete($normativa->pdf_path);
            }
            $data['pdf_path'] = $request->file('pdf')->store('normativa', 'public');
        }

        $normativa->fill($data)->save();

        return response()->json($this->adminArray($normativa));
    }

    /**
     * Delete a document (and its PDF).
     */
    public function destroy(NormativaDocumento $normativa): JsonResponse
    {
        if ($normativa->pdf_path) {
            Storage::disk('public')->delete($normativa->pdf_path);
        }

        $normativa->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        $data = $request->validate([
            'titulo' => [$required, 'string', 'max:300'],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'categoria' => [$required, 'in:ley_organica,decreto,orden,resolucion,instrucciones,otro'],
            'comunidad_autonoma' => [$required, 'string', 'max:100'],
            'especialidad_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'cuerpo' => ['sometimes', 'nullable', 'string', 'max:50'],
            'url_oficial' => ['sometimes', 'nullable', 'url', 'max:500'],
            'fecha_publicacion' => ['sometimes', 'nullable', 'date'],
            'vigente' => ['sometimes', 'boolean'],
            'pdf' => ['sometimes', 'nullable', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        // 'pdf' is the upload field; pdf_path is derived from it, never set directly.
        unset($data['pdf']);

        return $data;
    }

    /** @return array<string, mixed> */
    private function adminArray(NormativaDocumento $d): array
    {
        return [
            'id' => $d->id,
            'titulo' => $d->titulo,
            'descripcion' => $d->descripcion,
            'categoria' => $d->categoria,
            'comunidad_autonoma' => $d->comunidad_autonoma,
            'especialidad_code' => $d->especialidad_code,
            'cuerpo' => $d->cuerpo,
            'url_oficial' => $d->url_oficial,
            'pdf_url' => $d->pdf_path ? Storage::disk('public')->url($d->pdf_path) : null,
            'fecha_publicacion' => $d->fecha_publicacion?->toDateString(),
            'vigente' => (bool) $d->vigente,
            'fuente' => $d->fuente ?? 'manual',
            'idioma' => $d->idioma,
            'updated_at' => $d->updated_at?->toIso8601String(),
        ];
    }
}
