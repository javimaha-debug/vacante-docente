<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Convocatoria;
use App\Models\ConvocatoriaAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvocatoriasController extends Controller
{
    /**
     * List convocatorias, optionally filtered by estado / comunidad / cuerpo.
     * Auto-detected entries still pending superadmin review are hidden from users.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'estado' => ['sometimes', 'nullable', 'in:rumor,anunciada,convocada,en_proceso,resuelta'],
            'comunidad' => ['sometimes', 'nullable', 'string', 'max:100'],
            'cuerpo' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $convocatorias = Convocatoria::query()
            ->where('pendiente_revision', false)
            ->when($data['estado'] ?? null, fn ($q, $e) => $q->where('estado', $e))
            ->when($data['comunidad'] ?? null, fn ($q, $c) => $q->where('comunidad_autonoma', $c))
            ->when($data['cuerpo'] ?? null, fn ($q, $c) => $q->where('cuerpo', $c))
            ->orderByRaw("CASE estado WHEN 'en_proceso' THEN 0 WHEN 'convocada' THEN 1 WHEN 'anunciada' THEN 2 WHEN 'rumor' THEN 3 ELSE 4 END")
            ->orderByRaw('COALESCE(fecha_oficial, fecha_estimada) IS NULL')
            ->orderBy('fecha_oficial')
            ->orderBy('fecha_estimada')
            ->get();

        $alerted = $this->alertedIds($request, $convocatorias->pluck('id')->all());

        return response()->json([
            'data' => $convocatorias->map(fn ($c) => $this->convocatoriaArray($c, $alerted)),
        ]);
    }

    /**
     * Convocatoria detail.
     */
    public function show(Request $request, Convocatoria $convocatoria): JsonResponse
    {
        $convocatoria->load('sourceDocument:id,title,source_url');
        $alerted = $this->alertedIds($request, [$convocatoria->id]);

        return response()->json($this->convocatoriaArray($convocatoria, $alerted) + [
            'source_document' => $convocatoria->sourceDocument ? [
                'id' => $convocatoria->sourceDocument->id,
                'titulo' => $convocatoria->sourceDocument->title,
                'url' => $convocatoria->sourceDocument->source_url,
            ] : null,
        ]);
    }

    /**
     * Toggle the current user's alert for a convocatoria.
     */
    public function toggleAlert(Request $request, Convocatoria $convocatoria): JsonResponse
    {
        $existing = ConvocatoriaAlert::where('user_id', $request->user()->id)
            ->where('convocatoria_id', $convocatoria->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['alert_active' => false]);
        }

        ConvocatoriaAlert::create([
            'user_id' => $request->user()->id,
            'convocatoria_id' => $convocatoria->id,
        ]);

        return response()->json(['alert_active' => true]);
    }

    /**
     * The subset of the given convocatoria ids the authenticated user follows.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function alertedIds(Request $request, array $ids): array
    {
        $user = $request->user();
        if (! $user || $ids === []) {
            return [];
        }

        return ConvocatoriaAlert::where('user_id', $user->id)
            ->whereIn('convocatoria_id', $ids)
            ->pluck('convocatoria_id')
            ->all();
    }

    /**
     * @param  array<int, int>  $alerted
     * @return array<string, mixed>
     */
    private function convocatoriaArray(Convocatoria $c, array $alerted = []): array
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
            'alert_active' => in_array($c->id, $alerted, true),
        ];
    }
}
