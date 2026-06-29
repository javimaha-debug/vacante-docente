<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NormativaDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NormativaController extends Controller
{
    /**
     * List normativa documents, optionally filtered.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categoria' => ['sometimes', 'nullable', 'in:ley_organica,decreto,orden,resolucion,instrucciones,otro'],
            'comunidad' => ['sometimes', 'nullable', 'string', 'max:100'],
            'especialidad' => ['sometimes', 'nullable', 'string', 'max:50'],
            'cuerpo' => ['sometimes', 'nullable', 'string', 'max:50'],
            'vigente' => ['sometimes', 'nullable', 'boolean'],
        ]);

        $docs = NormativaDocumento::query()
            ->when($data['categoria'] ?? null, fn ($q, $c) => $q->where('categoria', $c))
            ->when($data['comunidad'] ?? null, fn ($q, $c) => $q->where('comunidad_autonoma', $c))
            ->when($data['cuerpo'] ?? null, fn ($q, $c) => $q->where(fn ($w) => $w->where('cuerpo', $c)->orWhereNull('cuerpo')))
            // A specialty filter also keeps the "todas" (null) documents.
            ->when($data['especialidad'] ?? null, fn ($q, $c) => $q->where(fn ($w) => $w->where('especialidad_code', $c)->orWhereNull('especialidad_code')))
            ->when(array_key_exists('vigente', $data) && $data['vigente'] !== null, fn ($q) => $q->where('vigente', $data['vigente']))
            ->orderByRaw("CASE categoria WHEN 'ley_organica' THEN 0 WHEN 'decreto' THEN 1 WHEN 'orden' THEN 2 WHEN 'resolucion' THEN 3 WHEN 'instrucciones' THEN 4 ELSE 5 END")
            ->orderByDesc('fecha_publicacion')
            ->orderBy('titulo')
            ->get();

        return response()->json(['data' => $docs->map(fn ($d) => $this->docArray($d))]);
    }

    /**
     * Document detail with resolved links.
     */
    public function show(NormativaDocumento $normativa): JsonResponse
    {
        return response()->json($this->docArray($normativa));
    }

    /** @return array<string, mixed> */
    private function docArray(NormativaDocumento $d): array
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
        ];
    }
}
