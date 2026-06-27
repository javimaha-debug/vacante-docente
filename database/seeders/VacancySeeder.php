<?php

namespace Database\Seeders;

use App\Models\Specialty;
use App\Models\Vacancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VacancySeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('vacantes_orientacion.json');

        if (! is_file($path)) {
            $this->command?->warn("vacantes_orientacion.json not found at {$path}; skipping VacancySeeder.");

            return;
        }

        // Orientación Educativa (Profesores de Enseñanza Secundaria), code 218.
        $specialty = Specialty::where('code', '218')
            ->where('education_level', 'secundaria')
            ->first();

        if (! $specialty) {
            $this->command?->error('Specialty 218 (Orientación Educativa) not found. Run SpecialtySeeder first.');

            return;
        }

        $records = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $now = Carbon::now();
        $year = 2025;

        // Re-seedable: clear this specialty's vacancies first.
        Vacancy::where('specialty_id', $specialty->id)->where('year', $year)->delete();

        $rows = collect($records)->map(function (array $r) use ($specialty, $now, $year) {
            $tags = $r['observ_tags'] ?? [];

            return [
                'specialty_id' => $specialty->id,
                'num' => (int) $r['num'],
                'provincia' => $r['provincia'],
                'localidad' => $r['localidad'],
                'centro_codigo' => (string) $r['codigo'],
                'centro_nombre' => $r['nombre'],
                'tipo_centro' => $r['tipo_centro'],
                'lloc' => (string) $r['lloc'],
                'req_ling' => (bool) ($r['req_ling'] ?? false),
                'observ' => ($r['observ'] ?? '') !== '' ? $r['observ'] : null,
                'observ_tags' => json_encode($tags, JSON_UNESCAPED_UNICODE),
                'year' => $year,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        $count = 0;
        foreach ($rows->chunk(100) as $chunk) {
            DB::table('vacancies')->insert($chunk->all());
            $count += $chunk->count();
        }

        $this->command?->info("Seeded {$count} vacancies for specialty {$specialty->code} – {$specialty->name}.");
    }
}
