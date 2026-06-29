<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Convocatoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvocatoriasController extends Controller
{
    /**
     * List convocatorias, optionally filtered by estado / comunidad / cuerpo.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'estado' => ['sometimes', 'nullable', 'in:rumor,anunciada,convocada,en_proceso,resuelta'],
            'comunidad' => ['sometimes', 'nullable', 'string', 'max:100'],
            'cuerpo' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $convocatorias = Convocatoria::query()
            ->when($data['estado'] ?? null, fn ($q, $e) => $q->where('estado', $e))
            ->when($data['comunidad'] ?? null, fn ($q, $c) => $q->where('comunidad_autonoma', $c))
            ->when($data['cuerpo'] ?? null, fn ($q, $c) => $q->where('cuerpo', $c))
            ->orderByRaw("CASE estado WHEN 'en_proceso' THEN 0 WHEN 'convocada' THEN 1 WHEN 'anunciada' THEN 2 WHEN 'rumor' THEN 3 ELSE 4 END")
            ->orderByRaw('COALESCE(fecha_oficial, fecha_estimada) IS NULL')
            ->orderBy('fecha_oficial')
            ->orderBy('fecha_estimada')
            ->get();

        return response()->json(['data' => $convocatorias->map(fn ($c) => $this->convocatoriaArray($c))]);
    }

    /**
     * Convocatoria detail.
     */
    public function show(Convocatoria $convocatoria): JsonResponse
    {
        $convocatoria->load('sourceDocument:id,titulo,url');

        return response()->json($this->convocatoriaArray($convocatoria) + [
            'source_document' => $convocatoria->sourceDocument ? [
                'id' => $convocatoria->sourceDocument->id,
                'titulo' => $convocatoria->sourceDocument->titulo,
                'url' => $convocatoria->sourceDocument->url,
            ] : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function convocatoriaArray(Convocatoria $c): array
    {
        return [
            'id' => $c->id,
            'titulo' => $c->titulo,
            'comunidad_autonoma' => $c->comunidad_autonoma,
            'cuerpo' => $c->cuerpo,
            'especialidades' => $c->especialidades ?? [],
            'estado' => $c->estado,
            'fecha_estimada' => $c->fecha_estimada?->toDateString(),
            'fecha_oficial' => $c->fecha_oficial?->toDateString(),
            'url_oficial' => $c->url_oficial,
            'boe_url' => $c->boe_url,
            'notas' => $c->notas,
            'source_document_id' => $c->source_document_id,
        ];
    }
}
