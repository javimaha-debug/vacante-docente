<?php

namespace App\Policies;

use App\Models\User;

/**
 * Resolves which plan-gated features a user can access.
 *
 * Plans declare a feature list (see PlanesSeeder). Some entries are aggregate
 * aliases ("todo_free", "todo_interino", ...) that expand into the concrete
 * feature set of another plan. This policy expands those aliases and answers
 * both the generic hasFeature() question and ten named capability checks used
 * across the app and the SPA's feature gating.
 */
class FeaturePolicy
{
    /**
     * Raw feature declarations per plan code (mirror of PlanesSeeder).
     *
     * @var array<string, array<int, string>>
     */
    private const PLAN_FEATURES = [
        'free' => [
            'explorador_basico', 'lista_30_vacantes', 'tablon_lectura',
            'ia_5_consultas_mes', 'monitor_gva',
        ],
        'interino' => [
            'todo_free', 'vacantes_ilimitadas', 'filtros_avanzados', 'exportar_ovidoc',
            'alertas_continuas', 'tablon_completo', 'calculadora_bolsa',
        ],
        'opositor' => [
            'todo_free', 'ia_ilimitada', 'normativa_ccaa', 'tests_flashcards',
            'simulador_oral', 'alertas_convocatorias', 'monitor_convocatorias',
        ],
        'docente_pro' => [
            'todo_free', 'herramientas_aula', 'normativa_vigente', 'asistente_nee',
            'banco_recursos',
        ],
        'todo_en_uno' => [
            'todo_interino', 'todo_opositor', 'todo_docente_pro',
        ],
    ];

    /** Aggregate aliases → the plan whose concrete features they expand to. */
    private const ALIASES = [
        'todo_free' => 'free',
        'todo_interino' => 'interino',
        'todo_opositor' => 'opositor',
        'todo_docente_pro' => 'docente_pro',
    ];

    /**
     * The complete catalogue of concrete (non-alias) feature keys. Used to
     * build the boolean `features` object the SPA consumes.
     *
     * @var array<int, string>
     */
    public const ALL_FEATURES = [
        'explorador_basico', 'lista_30_vacantes', 'tablon_lectura', 'ia_5_consultas_mes',
        'monitor_gva', 'vacantes_ilimitadas', 'filtros_avanzados', 'exportar_ovidoc',
        'alertas_continuas', 'tablon_completo', 'calculadora_bolsa', 'ia_ilimitada',
        'normativa_ccaa', 'tests_flashcards', 'simulador_oral', 'alertas_convocatorias',
        'monitor_convocatorias', 'herramientas_aula', 'normativa_vigente', 'asistente_nee',
        'banco_recursos',
    ];

    /**
     * Expand a plan's declared features into the flat set of concrete features,
     * resolving aggregate aliases recursively.
     *
     * @return array<int, string>
     */
    public function resolveFeatures(?string $planCodigo): array
    {
        $plan = $planCodigo ?: 'free';
        $declared = self::PLAN_FEATURES[$plan] ?? self::PLAN_FEATURES['free'];

        $resolved = [];
        foreach ($declared as $feature) {
            if (isset(self::ALIASES[$feature])) {
                $resolved = array_merge($resolved, $this->resolveFeatures(self::ALIASES[$feature]));
            } else {
                $resolved[] = $feature;
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * Whether the user's current plan grants the given concrete feature.
     *
     * TEMPORARY: feature gating is disabled — every feature is open to every
     * user regardless of plan. The plan resolution logic above is kept intact;
     * to re-enable gating, restore the commented-out body below.
     */
    public function hasFeature(User $user, string $feature): bool
    {
        return true;

        // if ($user->isSuperAdmin()) {
        //     return true;
        // }
        //
        // return in_array($feature, $this->resolveFeatures($user->plan), true);
    }

    /**
     * The boolean feature map (every concrete feature → granted?) exposed to the
     * SPA via GET /user/profile so components can gate UI.
     *
     * TEMPORARY: every feature is reported as granted (open access). To
     * re-enable gating, restore the commented-out body below.
     *
     * @return array<string, bool>
     */
    public function featureMap(User $user): array
    {
        return array_fill_keys(self::ALL_FEATURES, true);

        // $granted = $user->isSuperAdmin() ? self::ALL_FEATURES : $this->resolveFeatures($user->plan);
        // $granted = array_flip($granted);
        //
        // $map = [];
        // foreach (self::ALL_FEATURES as $feature) {
        //     $map[$feature] = isset($granted[$feature]);
        // }
        //
        // return $map;
    }

    // --- Ten named capability checks (used for authorization + gating) ---

    public function explorador(User $user): bool
    {
        return $this->hasFeature($user, 'explorador_basico');
    }

    public function vacantesIlimitadas(User $user): bool
    {
        return $this->hasFeature($user, 'vacantes_ilimitadas');
    }

    public function filtrosAvanzados(User $user): bool
    {
        return $this->hasFeature($user, 'filtros_avanzados');
    }

    public function exportar(User $user): bool
    {
        return $this->hasFeature($user, 'exportar_ovidoc');
    }

    public function alertasContinuas(User $user): bool
    {
        return $this->hasFeature($user, 'alertas_continuas');
    }

    public function publicarTablon(User $user): bool
    {
        return $this->hasFeature($user, 'tablon_completo');
    }

    public function iaIlimitada(User $user): bool
    {
        return $this->hasFeature($user, 'ia_ilimitada');
    }

    public function normativa(User $user): bool
    {
        return $this->hasFeature($user, 'normativa_ccaa') || $this->hasFeature($user, 'normativa_vigente');
    }

    public function herramientasAula(User $user): bool
    {
        return $this->hasFeature($user, 'herramientas_aula');
    }

    public function bancoRecursos(User $user): bool
    {
        return $this->hasFeature($user, 'banco_recursos');
    }
}
