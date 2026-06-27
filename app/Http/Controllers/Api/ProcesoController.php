<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VacancyResource;
use App\Models\Proceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProcesoController extends Controller
{
    /**
     * All procesos with estado, colectivo, dates and vacancy counts.
     */
    public function index(): JsonResponse
    {
        $procesos = Proceso::query()
            ->with('colectivo:id,code,name,body')
            ->withCount('vacancies')
            ->orderByDesc('anyo')
            ->orderByRaw("CASE estado WHEN 'publicado' THEN 0 WHEN 'pendiente' THEN 1 ELSE 2 END")
            ->get()
            ->map(fn (Proceso $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'anyo' => $p->anyo,
                'curso' => $p->curso,
                'estado' => $p->estado,
                'colectivo' => $p->colectivo,
                'ccaa_id' => $p->ccaa_id,
                'fecha_publicacion_vacantes' => $p->fecha_publicacion_vacantes?->toDateString(),
                'fecha_inicio_peticiones' => $p->fecha_inicio_peticiones?->toDateString(),
                'fecha_fin_peticiones' => $p->fecha_fin_peticiones?->toDateString(),
                'fecha_adjudicacion' => $p->fecha_adjudicacion?->toDateString(),
                'vacancies_count' => $p->vacancies_count,
            ]);

        return response()->json(['data' => $procesos]);
    }

    /**
     * Vacancies for a proceso, with the standard explorer filters.
     */
    public function vacantes(Request $request, Proceso $proceso): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'especialidad' => ['sometimes', 'nullable', 'integer', 'exists:specialties,id'],
            'provincia' => ['sometimes', 'in:Alacant,Castelló,València'],
            'localitat' => ['sometimes', 'nullable', 'string', 'max:200'],
            'tipo_centro' => ['sometimes', 'array'],
            'tipo_centro.*' => ['in:Secundaria,Primaria/Infantil,Otro'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:200'],
            'req_ling' => ['sometimes', 'boolean'],
            'itinerante' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        $query = $proceso->vacancies()->getQuery();

        if (! empty($validated['especialidad'])) {
            $query->where('specialty_id', $validated['especialidad']);
        }
        if (! empty($validated['provincia'])) {
            $query->where('provincia', $validated['provincia']);
        }
        if (! empty($validated['localitat'])) {
            $query->where('localidad', 'like', '%'.$validated['localitat'].'%');
        }
        if (! empty($validated['tipo_centro'])) {
            $query->whereIn('tipo_centro', $validated['tipo_centro']);
        }
        if (! empty($validated['observaciones'])) {
            $term = '%'.$validated['observaciones'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('observaciones', 'like', $term)->orWhere('observ', 'like', $term);
            });
        }
        if ($request->boolean('req_ling')) {
            $query->where(fn ($q) => $q->where('requisito_linguistico', true)->orWhere('req_ling', true));
        }
        if ($request->boolean('itinerante')) {
            $query->where('itinerante', true);
        }

        $paginator = $query
            ->orderBy('provincia')
            ->orderBy('localidad')
            ->orderBy('centro_nombre')
            ->paginate((int) ($validated['per_page'] ?? 100))
            ->withQueryString();

        return VacancyResource::collection($paginator);
    }
}
