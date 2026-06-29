<?php

namespace Database\Seeders;

use App\Models\Convocatoria;
use Illuminate\Database\Seeder;

class ConvocatoriaSeeder extends Seeder
{
    /**
     * Seed the current/known CV convocatorias. Idempotent: matches on titulo.
     */
    public function run(): void
    {
        $convocatorias = [
            [
                'titulo' => 'Oposiciones Ingreso Cuerpo Maestros CV 2025',
                'comunidad_autonoma' => 'valenciana',
                'cuerpo' => 'maestros',
                'estado' => 'en_proceso',
                'url_oficial' => 'https://ceice.gva.es/es/web/rrhh-educacion',
            ],
            [
                'titulo' => 'Oposiciones Secundaria CV 2025 — Adquisición nuevas especialidades',
                'comunidad_autonoma' => 'valenciana',
                'cuerpo' => 'secundaria',
                'estado' => 'en_proceso',
            ],
            [
                'titulo' => 'Oposiciones Secundaria CV 2027 — Estimada',
                'comunidad_autonoma' => 'valenciana',
                'cuerpo' => 'secundaria',
                'estado' => 'rumor',
                'fecha_estimada' => '2027-06-01',
            ],
            // Estimaciones 2026-2027 a partir de información pública (sindicatos /
            // oferta de empleo). El superadmin confirma estado y fechas reales.
            [
                'titulo' => 'Oposiciones Maestros CV 2026 (estimada)',
                'comunidad_autonoma' => 'valenciana',
                'cuerpo' => 'maestros',
                'estado' => 'rumor',
                'fecha_estimada' => '2026-06-20',
                'notas' => 'Estimación según oferta pública de empleo docente. Pendiente de convocatoria oficial.',
            ],
            [
                'titulo' => 'Oposiciones FP CV 2026 (estimada)',
                'comunidad_autonoma' => 'valenciana',
                'cuerpo' => 'fp',
                'estado' => 'rumor',
                'fecha_estimada' => '2026-06-20',
                'notas' => 'Estimación. Pendiente de confirmación oficial de la Conselleria.',
            ],
            [
                'titulo' => 'Oposiciones Ingreso Cuerpos Docentes (nacional) 2026 (estimada)',
                'comunidad_autonoma' => 'nacional',
                'cuerpo' => 'secundaria',
                'estado' => 'rumor',
                'fecha_estimada' => '2026-06-01',
                'notas' => 'Marco estatal estimado; cada comunidad publica su convocatoria.',
            ],
        ];

        foreach ($convocatorias as $c) {
            Convocatoria::updateOrCreate(
                ['titulo' => $c['titulo']],
                $c,
            );
        }
    }
}
