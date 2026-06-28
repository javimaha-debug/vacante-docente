<?php

namespace App\Services;

use App\Models\ParticipanteProceso;
use App\Models\Proceso;
use App\Models\User;
use App\Models\Vacancy;
use App\Notifications\ListadoActualizado;
use Illuminate\Support\Facades\Notification;

/**
 * Resolves which teachers should be told that a listing changed and dispatches
 * the multi-channel notification to them.
 */
class ListadoNotificacionService
{
    /**
     * Notify teachers affected by changes in a proceso's vacancy listing.
     * Recipients are users who follow (have in their profile) any of the
     * specialties touched by the new/modified vacancies.
     *
     * @param  array<string, int>  $resumen
     */
    public function notifyVacantes(Proceso $proceso, array $resumen): int
    {
        if (($resumen['nuevas'] ?? 0) + ($resumen['modificadas'] ?? 0) + ($resumen['eliminadas'] ?? 0) === 0) {
            return 0;
        }

        $specialtyIds = Vacancy::where('proceso_id', $proceso->id)
            ->whereIn('cambio', ['nueva', 'modificada'])
            ->distinct()
            ->pluck('specialty_id')
            ->filter()
            ->all();

        if (empty($specialtyIds)) {
            // Removals only (no rows survive to flag): notify anyone following
            // any specialty present in this proceso.
            $specialtyIds = Vacancy::where('proceso_id', $proceso->id)->distinct()->pluck('specialty_id')->filter()->all();
        }

        $users = User::whereHas('especialidades', fn ($q) => $q->whereIn('specialty_id', $specialtyIds))->get();

        Notification::send($users, new ListadoActualizado($proceso, 'vacantes', $resumen));

        return $users->count();
    }

    /**
     * Notify teachers whose own participant entry changed (matched by nombre_gva).
     *
     * @param  array<string, int>  $resumen
     */
    public function notifyParticipantes(Proceso $proceso, array $resumen): int
    {
        if (($resumen['nuevos'] ?? 0) + ($resumen['modificados'] ?? 0) === 0) {
            return 0;
        }

        $nombres = ParticipanteProceso::where('proceso_id', $proceso->id)
            ->whereIn('cambio', ['nuevo', 'modificado'])
            ->pluck('nombre_gva')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))
            ->unique()
            ->all();

        if (empty($nombres)) {
            return 0;
        }

        $users = User::whereNotNull('nombre_gva')
            ->whereRaw('LOWER(nombre_gva) IN ('.implode(',', array_fill(0, count($nombres), '?')).')', $nombres)
            ->get();

        Notification::send($users, new ListadoActualizado($proceso, 'participantes', $resumen));

        return $users->count();
    }
}
