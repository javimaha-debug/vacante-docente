<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParticipanteImportacion;
use App\Models\ParticipanteProceso;
use App\Models\Proceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipanteController extends Controller
{
    /**
     * Summary of the latest participant-list import (for the "lista
     * actualizada" banner). Only meaningful when the last import had changes.
     */
    public function cambios(Proceso $proceso): JsonResponse
    {
        $last = ParticipanteImportacion::where('proceso_id', $proceso->id)
            ->orderByDesc('importado_en')
            ->first();

        $hasChanges = $last && ! $last->es_primera
            && ($last->nuevos > 0 || $last->modificados > 0 || $last->eliminados > 0);

        return response()->json([
            'has_changes' => (bool) $hasChanges,
            'importado_en' => $last?->importado_en?->toIso8601String(),
            'nuevos' => $last?->nuevos ?? 0,
            'modificados' => $last?->modificados ?? 0,
            'eliminados' => $last?->eliminados ?? 0,
        ]);
    }

    /**
     * Full participant list for a proceso (public, paginated, searchable).
     */
    public function index(Request $request, Proceso $proceso): JsonResponse
    {
        $request->validate([
            'nombre' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $query = ParticipanteProceso::query()
            ->where('proceso_id', $proceso->id)
            ->orderBy('posicion');

        if ($nombre = $request->string('nombre')->trim()->value()) {
            $query->where('nombre_gva', 'like', '%'.$nombre.'%');
        }

        return response()->json($query->paginate(100)->withQueryString());
    }

    /**
     * The authenticated user's own position in the participant list, matched
     * by their nombre_gva profile field.
     */
    public function miPosicion(Request $request, Proceso $proceso): JsonResponse
    {
        $user = $request->user();

        if (! $user->nombre_gva) {
            return response()->json([
                'found' => false,
                'message' => 'Configura tu nombre GVA en el perfil para localizarte en la lista.',
            ], 422);
        }

        $matches = ParticipanteProceso::query()
            ->where('proceso_id', $proceso->id)
            ->whereRaw('LOWER(nombre_gva) = ?', [mb_strtolower($user->nombre_gva)])
            ->get();

        if ($matches->isEmpty()) {
            return response()->json([
                'found' => false,
                'listado_fecha' => $this->listadoFecha($proceso),
            ]);
        }

        // Sectioned listings hold one row per (persona × especialidad). Prefer
        // the row matching one of the user's own specialty codes.
        $userCodes = $user->especialidades()->with('specialty')->get()
            ->flatMap(fn ($e) => [$e->specialty?->codigo, $e->specialty?->code])
            ->filter()
            ->map(fn ($c) => (string) $c)
            ->all();

        $participante = $matches->first(fn ($p) => in_array((string) $p->especialidad_codigo, $userCodes, true))
            ?? $matches->first();

        return response()->json([
            'found' => true,
            'posicion' => $participante->posicion,
            'estado' => $participante->estado,
            'cambio' => $participante->cambio,
            'especialidad_codigo' => $participante->especialidad_codigo,
            'listado_fecha' => $this->listadoFecha($proceso),
            'adjudicacion' => $participante->estado === 'Adjudicat' ? [
                'lloc' => $participante->lloc_adjudicado,
                'centro_nombre' => $participante->centro_nombre,
                'localitat' => $participante->localitat,
                'especialidad_codigo' => $participante->especialidad_codigo,
                'jornada' => $participante->jornada,
            ] : null,
        ]);
    }

    /**
     * Date of the most recent participant-list import for a proceso, if any.
     */
    private function listadoFecha(Proceso $proceso): ?string
    {
        return ParticipanteImportacion::where('proceso_id', $proceso->id)
            ->orderByDesc('importado_en')
            ->first()?->importado_en?->toDateString();
    }
}
