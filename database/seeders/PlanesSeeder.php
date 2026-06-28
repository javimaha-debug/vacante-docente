<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanesSeeder extends Seeder
{
    /**
     * Seed the available plans. Prices are intentionally left at 0/null for
     * Fase 0 — pricing is decided later. What matters here is the feature set
     * each plan unlocks (see App\Policies\FeaturePolicy).
     */
    public function run(): void
    {
        $planes = [
            [
                'codigo' => 'free',
                'nombre' => 'Gratis',
                'descripcion' => 'Para empezar a explorar vacantes y el funcionamiento de la bolsa.',
                'sort_order' => 0,
                'features' => [
                    'explorador_basico',
                    'lista_30_vacantes',
                    'tablon_lectura',
                    'ia_5_consultas_mes',
                    'monitor_gva',
                ],
            ],
            [
                'codigo' => 'interino',
                'nombre' => 'Interino',
                'descripcion' => 'Todo lo que necesitas para gestionar tu bolsa de interinidades.',
                'sort_order' => 1,
                'features' => [
                    'todo_free',
                    'vacantes_ilimitadas',
                    'filtros_avanzados',
                    'exportar_ovidoc',
                    'alertas_continuas',
                    'tablon_completo',
                    'calculadora_bolsa',
                ],
            ],
            [
                'codigo' => 'opositor',
                'nombre' => 'Opositor',
                'descripcion' => 'Preparación de oposiciones con IA, normativa y simuladores.',
                'sort_order' => 2,
                'features' => [
                    'todo_free',
                    'ia_ilimitada',
                    'normativa_ccaa',
                    'tests_flashcards',
                    'simulador_oral',
                    'alertas_convocatorias',
                    'monitor_convocatorias',
                ],
            ],
            [
                'codigo' => 'docente_pro',
                'nombre' => 'Docente Pro',
                'descripcion' => 'Herramientas de aula, normativa vigente y recursos para el día a día.',
                'sort_order' => 3,
                'features' => [
                    'todo_free',
                    'herramientas_aula',
                    'normativa_vigente',
                    'asistente_nee',
                    'banco_recursos',
                ],
            ],
            [
                'codigo' => 'todo_en_uno',
                'nombre' => 'Todo en Uno',
                'descripcion' => 'Acceso completo a todas las funcionalidades de la plataforma.',
                'sort_order' => 4,
                'features' => [
                    'todo_interino',
                    'todo_opositor',
                    'todo_docente_pro',
                ],
            ],
        ];

        foreach ($planes as $plan) {
            Plan::updateOrCreate(
                ['codigo' => $plan['codigo']],
                [
                    'nombre' => $plan['nombre'],
                    'descripcion' => $plan['descripcion'],
                    'precio_mensual' => 0,
                    'precio_anual' => null,
                    'precio_temporada' => null,
                    'activo' => true,
                    'features' => $plan['features'],
                    'sort_order' => $plan['sort_order'],
                ],
            );
        }
    }
}
