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
        ];

        foreach ($convocatorias as $c) {
            Convocatoria::updateOrCreate(
                ['titulo' => $c['titulo']],
                $c,
            );
        }
    }
}
