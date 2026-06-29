<?php

namespace Database\Seeders;

use App\Models\NormativaDocumento;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class NormativaSeeder extends Seeder
{
    /**
     * Seed the key CV / nacional normativa used by opositores. Idempotent:
     * matches on titulo so re-running keeps a single row per document.
     */
    public function run(): void
    {
        $publishedBy = User::where('role', 'superadmin')->value('id');
        $now = Carbon::now();

        $docs = [
            [
                'titulo' => 'LOE — Ley Orgánica 2/2006 de Educación (consolidada)',
                'categoria' => 'ley_organica',
                'comunidad_autonoma' => 'nacional',
                'url_oficial' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-7899',
                'vigente' => true,
            ],
            [
                'titulo' => 'LOMLOE — Ley Orgánica 3/2020 que modifica la LOE',
                'categoria' => 'ley_organica',
                'comunidad_autonoma' => 'nacional',
                'url_oficial' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2020-17264',
                'vigente' => true,
            ],
            [
                'titulo' => 'Decreto 108/2014 — Currículum ESO Comunitat Valenciana',
                'categoria' => 'decreto',
                'comunidad_autonoma' => 'valenciana',
                'url_oficial' => 'https://dogv.gva.es/datos/2014/07/31/pdf/2014_7347.pdf',
                'vigente' => true,
            ],
            [
                'titulo' => 'Decreto 87/2015 — Currículum Batxillerat CV',
                'categoria' => 'decreto',
                'comunidad_autonoma' => 'valenciana',
                'vigente' => true,
            ],
            [
                'titulo' => 'Orden 5/2021 — EDEN y OVIDOC',
                'categoria' => 'orden',
                'comunidad_autonoma' => 'valenciana',
                'vigente' => true,
            ],
        ];

        foreach ($docs as $doc) {
            NormativaDocumento::updateOrCreate(
                ['titulo' => $doc['titulo']],
                array_merge($doc, [
                    'published_by' => $publishedBy,
                    'published_at' => $now,
                ]),
            );
        }
    }
}
