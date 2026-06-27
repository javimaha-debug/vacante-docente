<?php

namespace App\Console\Commands;

use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\Proceso;
use Illuminate\Console\Command;

class CreateCurrentProcesos extends Command
{
    protected $signature = 'procesos:create-current';

    protected $description = 'Create the placement procesos for the current 2026-2027 course (CV).';

    public function handle(): int
    {
        $cv = Ccaa::where('code', 'CV')->first();

        if (! $cv) {
            $this->error('CCAA "CV" not found. Run: php artisan db:seed --class=CcaaSeeder');

            return self::FAILURE;
        }

        $anyo = 2026;
        $curso = '2026-2027';

        // [colectivo code, colectivo body, proceso name, estado]
        $procesos = [
            ['SUPRIMIDO', 'SECUNDARIA', 'Suprimits Secundària 2026-2027', 'publicado'],
            ['SUPRIMIDO', 'MAESTROS', 'Suprimits Mestres 2026-2027', 'publicado'],
            ['COMISION_SERVICIO', 'SECUNDARIA', 'Comissions Secundària 2026-2027', 'pendiente'],
            ['COMISION_SERVICIO', 'MAESTROS', 'Comissions Mestres 2026-2027', 'pendiente'],
            ['INTERINO', 'SECUNDARIA', 'Interins Secundària 2026-2027', 'pendiente'],
            ['INTERINO', 'MAESTROS', 'Interins Mestres 2026-2027', 'pendiente'],
        ];

        $created = 0;
        $existing = 0;

        foreach ($procesos as [$code, $body, $nombre, $estado]) {
            $colectivo = Colectivo::where('ccaa_id', $cv->id)
                ->where('code', $code)
                ->where('body', $body)
                ->first();

            if (! $colectivo) {
                $this->warn("Skipping '{$nombre}': colectivo {$code}/{$body} not found (run ColectivoSeeder).");

                continue;
            }

            $proceso = Proceso::firstOrCreate(
                [
                    'ccaa_id' => $cv->id,
                    'colectivo_id' => $colectivo->id,
                    'anyo' => $anyo,
                    'curso' => $curso,
                ],
                [
                    'nombre' => $nombre,
                    'estado' => $estado,
                ],
            );

            if ($proceso->wasRecentlyCreated) {
                $created++;
                $this->line("  created: {$nombre} [{$estado}]");
            } else {
                $existing++;
                $this->line("  exists:  {$nombre}");
            }
        }

        $this->info("Done. {$created} proceso(s) created, {$existing} already existed.");

        return self::SUCCESS;
    }
}
