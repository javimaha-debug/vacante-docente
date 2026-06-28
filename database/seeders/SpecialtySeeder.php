<?php

namespace Database\Seeders;

use App\Models\Ccaa;
use App\Models\Specialty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $ccaaId = Ccaa::where('code', 'CV')->value('id');

        // Maps the internal education_level to the GVA `cuerpo` label.
        $cuerpoMap = [
            'maestros' => 'MAESTROS',
            'secundaria' => 'SECUNDARIA',
            'fp' => 'FP',
        ];

        // Maestros: the interim bolsa carries habilitaciones (INF PRI ING FRA
        // EF MUS PT AL); these are the catalogue specialties they map to.
        $maestros = [
            ['code' => '120', 'name' => 'Educación Infantil', 'body' => 'Maestros', 'level' => 'maestros'],
            ['code' => '121', 'name' => 'Educación Primaria', 'body' => 'Maestros', 'level' => 'maestros'],
            ['code' => '122', 'name' => 'Lengua Extranjera: Inglés', 'body' => 'Maestros', 'level' => 'maestros'],
            ['code' => '123', 'name' => 'Educación Física', 'body' => 'Maestros', 'level' => 'maestros'],
            ['code' => '124', 'name' => 'Música', 'body' => 'Maestros', 'level' => 'maestros'],
            ['code' => '125', 'name' => 'Audición y Lenguaje', 'body' => 'Maestros', 'level' => 'maestros'],
            ['code' => '126', 'name' => 'Pedagogía Terapéutica', 'body' => 'Maestros', 'level' => 'maestros'],
            ['code' => '127', 'name' => 'Lengua Extranjera: Francés', 'body' => 'Maestros', 'level' => 'maestros'],
            // Maestros positions that appear only in the suprimidos listings.
            ['code' => '151', 'name' => 'Educación Especial: Audición y Lenguaje', 'body' => 'Maestros', 'level' => 'maestros'],
            ['code' => '152', 'name' => 'Educación Especial: Pedagogía Terapéutica', 'body' => 'Maestros', 'level' => 'maestros'],
            ['code' => '153', 'name' => 'Formación de Personas Adultas: Primaria', 'body' => 'Maestros', 'level' => 'maestros'],
        ];

        // Secundaria + FP + EOI + Música/Artes + Artes Plásticas: the REAL GVA
        // codes, extracted from the official 2026-2027 interim listing. These
        // are the codes used by both the vacancy and participant imports, so
        // matching by code is exact.
        $secundaria = require database_path('data/specialties_gva_2026.php');

        // Specialties that appear in the suprimidos vacancy listings but not in
        // the interim bolsa listing (ESO ámbitos, FPA, acuicultura).
        $extra = [
            ['code' => '267', 'name' => 'Procesos de Cultivo Acuícola', 'body' => 'Profesores de Enseñanza Secundaria', 'level' => 'secundaria'],
            ['code' => '276', 'name' => 'Ámbito Científico y Técnico', 'body' => 'Profesores de Enseñanza Secundaria', 'level' => 'secundaria'],
            ['code' => '277', 'name' => 'Ámbito Sociolingüístico', 'body' => 'Profesores de Enseñanza Secundaria', 'level' => 'secundaria'],
            ['code' => '293', 'name' => 'FPA Ciencias Sociales', 'body' => 'Profesores de Enseñanza Secundaria', 'level' => 'secundaria'],
            ['code' => '294', 'name' => 'FPA Comunicación (Inglés)', 'body' => 'Profesores de Enseñanza Secundaria', 'level' => 'secundaria'],
            ['code' => '297', 'name' => 'FPA Comunicación (Valenciano)', 'body' => 'Profesores de Enseñanza Secundaria', 'level' => 'secundaria'],
        ];
        $secundaria = array_merge($secundaria, $extra);

        $rows = [];
        foreach (array_merge($maestros, $secundaria) as $s) {
            $rows[] = [
                'code' => (string) $s['code'],
                'name' => $s['name'],
                'body' => $s['body'],
                'education_level' => $s['level'],
                'ccaa_id' => $ccaaId,
                'codigo' => (string) $s['code'],
                'cuerpo' => $cuerpoMap[$s['level']] ?? null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Idempotent: upsert on (code, education_level).
        Specialty::upsert(
            $rows,
            ['code', 'education_level'],
            ['name', 'body', 'ccaa_id', 'codigo', 'cuerpo', 'is_active', 'updated_at']
        );

        // Deactivate any leftover fabricated specialties (the previous catalogue
        // used invented sequential codes that collided across cuerpos and
        // duplicated some specialties, e.g. two "Orientación Educativa"). We
        // deactivate rather than delete to preserve FKs (vacancies/users
        // cascade on delete); new imports resolve by real GVA code.
        $keep = array_map(fn ($r) => $r['code'].'|'.$r['education_level'], $rows);
        Specialty::whereNotIn(DB::raw("code || '|' || education_level"), $keep)
            ->update(['is_active' => false, 'updated_at' => $now]);
    }
}
