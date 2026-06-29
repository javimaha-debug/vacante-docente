<?php

namespace App\Http\Controllers\Api\Docente;

use App\Http\Controllers\Controller;
use App\Models\DocenteExamen;
use App\Models\DocenteRecursoCompartido;
use App\Models\DocenteRecursoValoracion;
use App\Models\DocenteRubrica;
use App\Models\DocenteSituacionAprendizaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BancoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = DocenteRecursoCompartido::where('moderado', true);

        if ($request->filled('tipo')) {
            $q->where('tipo', $request->input('tipo'));
        }
        if ($request->filled('etapa') || $request->filled('asignatura') || $request->filled('curso')) {
            // Filter by joined resource attributes — simplified: eager load and filter
        }

        $recursos = $q->orderByDesc('valoracion_media')
            ->paginate(20);

        // Resolve resource details without exposing author
        $recursos->getCollection()->transform(fn ($r) => $this->decorateRecurso($r));

        return response()->json($recursos);
    }

    public function show(Request $request, DocenteRecursoCompartido $recurso): JsonResponse
    {
        abort_if(! $recurso->moderado, 404);

        return response()->json($this->decorateRecurso($recurso, full: true));
    }

    public function usar(Request $request, DocenteRecursoCompartido $recurso): JsonResponse
    {
        abort_if(! $recurso->moderado, 404);

        $userId = $request->user()->id;
        $copia = null;

        DB::transaction(function () use ($recurso, $userId, &$copia) {
            match ($recurso->tipo) {
                'rubrica' => $copia = $this->copiarRubrica($recurso->recurso_id, $userId),
                'situacion_aprendizaje' => $copia = $this->copiarSituacion($recurso->recurso_id, $userId),
                'examen' => $copia = $this->copiarExamen($recurso->recurso_id, $userId),
                default => null,
            };
            $recurso->increment('num_descargas');
            DocenteRubrica::find($recurso->recurso_id)?->increment('veces_usada');
            DocenteSituacionAprendizaje::find($recurso->recurso_id)?->increment('veces_usada');
        });

        return response()->json(['copiado' => true, 'id' => $copia?->id]);
    }

    public function valorar(Request $request, DocenteRecursoCompartido $recurso): JsonResponse
    {
        abort_if(! $recurso->moderado, 404);

        $data = $request->validate([
            'puntuacion' => ['required', 'integer', 'min:1', 'max:5'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ]);

        DocenteRecursoValoracion::updateOrCreate(
            ['user_id' => $request->user()->id, 'recurso_compartido_id' => $recurso->id],
            array_merge($data, ['created_at' => now()])
        );

        // Recalculate average
        $avg = DocenteRecursoValoracion::where('recurso_compartido_id', $recurso->id)->avg('puntuacion');
        $count = DocenteRecursoValoracion::where('recurso_compartido_id', $recurso->id)->count();
        $recurso->update(['valoracion_media' => round($avg, 2), 'num_valoraciones' => $count]);

        return response()->json(['valorado' => true, 'nueva_media' => round($avg, 2)]);
    }

    private function decorateRecurso(DocenteRecursoCompartido $r, bool $full = false): array
    {
        $detalle = match ($r->tipo) {
            'rubrica' => DocenteRubrica::find($r->recurso_id)?->only($full
                ? ['id', 'titulo', 'descripcion', 'tipo_tarea', 'etapa', 'criterios']
                : ['id', 'titulo', 'tipo_tarea', 'etapa']),
            'situacion_aprendizaje' => DocenteSituacionAprendizaje::find($r->recurso_id)?->only($full
                ? ['id', 'titulo', 'descripcion', 'etapa', 'curso', 'asignatura', 'actividades', 'criterios_evaluacion']
                : ['id', 'titulo', 'etapa', 'curso', 'asignatura']),
            'examen' => DocenteExamen::find($r->recurso_id)?->only($full
                ? ['id', 'titulo', 'tipo', 'tiempo_minutos', 'preguntas']
                : ['id', 'titulo', 'tipo', 'tiempo_minutos']),
            default => null,
        };

        return [
            'id' => $r->id,
            'tipo' => $r->tipo,
            'valoracion_media' => $r->valoracion_media,
            'num_valoraciones' => $r->num_valoraciones,
            'num_descargas' => $r->num_descargas,
            'recurso' => $detalle,
        ];
    }

    private function copiarRubrica(int $id, int $userId): ?DocenteRubrica
    {
        $original = DocenteRubrica::find($id);
        if (! $original) return null;
        $copia = $original->replicate(['user_id', 'es_publica', 'veces_usada']);
        $copia->user_id = $userId;
        $copia->es_publica = false;
        $copia->veces_usada = 0;
        $copia->titulo = '[Copia] ' . $original->titulo;
        $copia->save();
        return $copia;
    }

    private function copiarSituacion(int $id, int $userId): ?DocenteSituacionAprendizaje
    {
        $original = DocenteSituacionAprendizaje::find($id);
        if (! $original) return null;
        $copia = $original->replicate(['user_id', 'es_publica', 'veces_usada']);
        $copia->user_id = $userId;
        $copia->es_publica = false;
        $copia->titulo = '[Copia] ' . $original->titulo;
        $copia->save();
        return $copia;
    }

    private function copiarExamen(int $id, int $userId): ?DocenteExamen
    {
        $original = DocenteExamen::find($id);
        if (! $original) return null;
        $copia = $original->replicate(['user_id']);
        $copia->user_id = $userId;
        $copia->titulo = '[Copia] ' . $original->titulo;
        $copia->save();
        return $copia;
    }
}
