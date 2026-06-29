<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Convocatoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvocatoriasController extends Controller
{
    /**
     * Full list for the admin table (includes linked source document).
     */
    public function index(Request $request): JsonResponse
    {
        $convocatorias = Convocatoria::query()
            ->with('sourceDocument:id,titulo')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $convocatorias->map(fn ($c) => $this->adminArray($c))]);
    }

    /**
     * Create a convocatoria.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request, true);

        $convocatoria = Convocatoria::create($data);
        $convocatoria->load('sourceDocument:id,titulo');

        return response()->json($this->adminArray($convocatoria), 201);
    }

    /**
     * Update estado / fechas / urls / links.
     */
    public function update(Request $request, Convocatoria $convocatoria): JsonResponse
    {
        $data = $this->validatePayload($request, false);

        $convocatoria->fill($data)->save();
        $convocatoria->load('sourceDocument:id,titulo');

        return response()->json($this->adminArray($convocatoria));
    }

    /**
     * Delete a convocatoria.
     */
    public function destroy(Convocatoria $convocatoria): JsonResponse
    {
        $convocatoria->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'titulo' => [$required, 'string', 'max:300'],
            'comunidad_autonoma' => [$required, 'string', 'max:100'],
            'cuerpo' => ['sometimes', 'nullable', 'string', 'max:50'],
            'especialidades' => ['sometimes', 'nullable', 'array'],
            'especialidades.*' => ['string', 'max:50'],
            'estado' => [$required, 'in:rumor,anunciada,convocada,en_proceso,resuelta'],
            'fecha_estimada' => ['sometimes', 'nullable', 'date'],
            'fecha_oficial' => ['sometimes', 'nullable', 'date'],
            'url_oficial' => ['sometimes', 'nullable', 'url', 'max:500'],
            'boe_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'notas' => ['sometimes', 'nullable', 'string'],
            'source_document_id' => ['sometimes', 'nullable', 'integer', 'exists:gva_noticias,id'],
        ]);
    }

    /** @return array<string, mixed> */
    private function adminArray(Convocatoria $c): array
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
            'source_document' => $c->sourceDocument ? [
                'id' => $c->sourceDocument->id,
                'titulo' => $c->sourceDocument->titulo,
            ] : null,
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }
}
