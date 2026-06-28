<?php

namespace App\Console\Commands;

use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\Proceso;
use Illuminate\Console\Command;

class CreateProcesos extends Command
{
    protected $signature = 'procesos:create {anyo : Año de inicio del curso (ej. 2024)}
                            {--curso= : Etiqueta del curso (por defecto anyo-siguiente)}
                            {--estado=cerrado : Estado de los procesos (publicado|pendiente|cerrado)}';

    protected $description = 'Crea los procesos (CV) de un curso concreto, útil para cargar histórico de años anteriores.';

    /** Colectivos × cuerpos que componen un curso. */
    private const MATRIX = [
        ['SUPRIMIDO', 'SECUNDARIA', 'Suprimits Secundària'],
        ['SUPRIMIDO', 'MAESTROS', 'Suprimits Mestres'],
        ['COMISION_SERVICIO', 'SECUNDARIA', 'Comissions Secundària'],
        ['COMISION_SERVICIO', 'MAESTROS', 'Comissions Mestres'],
        ['INTERINO', 'SECUNDARIA', 'Interins Secundària'],
        ['INTERINO', 'MAESTROS', 'Interins Mestres'],
    ];

    public function handle(): int
    {
        $cv = Ccaa::where('code', 'CV')->first();
        if (! $cv) {
            $this->error('CCAA "CV" no encontrada. Ejecuta: php artisan db:seed --class=CcaaSeeder');

            return self::FAILURE;
        }

        $anyo = (int) $this->argument('anyo');
        if ($anyo < 2000 || $anyo > 2100) {
            $this->error('Año no válido.');

            return self::FAILURE;
        }

        $curso = (string) ($this->option('curso') ?: $anyo.'-'.($anyo + 1));
        $estado = (string) $this->option('estado');
        if (! in_array($estado, ['publicado', 'pendiente', 'cerrado'], true)) {
            $this->error('Estado no válido (publicado|pendiente|cerrado).');

            return self::FAILURE;
        }

        $created = $existing = 0;

        foreach (self::MATRIX as [$code, $body, $base]) {
            $colectivo = Colectivo::where('ccaa_id', $cv->id)->where('code', $code)->where('body', $body)->first();
            if (! $colectivo) {
                $this->warn("Omitido '{$base} {$curso}': colectivo {$code}/{$body} no existe (ColectivoSeeder).");

                continue;
            }

            $proceso = Proceso::firstOrCreate(
                ['ccaa_id' => $cv->id, 'colectivo_id' => $colectivo->id, 'anyo' => $anyo, 'curso' => $curso],
                ['nombre' => "{$base} {$curso}", 'estado' => $estado],
            );

            if ($proceso->wasRecentlyCreated) {
                $created++;
                $this->line("  creado:  {$proceso->nombre} (#{$proceso->id}) [{$estado}]");
            } else {
                $existing++;
                $this->line("  existe:  {$proceso->nombre} (#{$proceso->id})");
            }
        }

        $this->info("Listo. {$created} proceso(s) creados, {$existing} ya existían para {$curso}.");
        $this->line('Importa después sus PDFs con: vacantes:import-pdf / participantes:import-pdf <ruta> <proceso_id>');

        return self::SUCCESS;
    }
}
