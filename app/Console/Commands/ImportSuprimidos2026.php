<?php

namespace App\Console\Commands;

use App\Models\Colectivo;
use App\Models\Proceso;
use App\Models\Vacancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class ImportSuprimidos2026 extends Command
{
    protected $signature = 'vacantes:import-suprimidos-2026
                            {--secundaria=pdfs/gva/suprimits-secundaria-2026.pdf : Storage path to the Secundària PDF}
                            {--primaria=pdfs/gva/suprimits-primaria-2026.pdf : Storage path to the Mestres PDF}';

    protected $description = 'Import the two 2026-2027 suprimidos PDFs (Secundària + Mestres) into their procesos.';

    public function handle(): int
    {
        $targets = [
            ['body' => 'SECUNDARIA', 'label' => 'Suprimits Secundària', 'path' => $this->option('secundaria')],
            ['body' => 'MAESTROS', 'label' => 'Suprimits Mestres', 'path' => $this->option('primaria')],
        ];

        $anyImported = false;

        foreach ($targets as $t) {
            $proceso = $this->resolveProceso($t['body']);

            if (! $proceso) {
                $this->warn("Proceso '{$t['label']} 2026-2027' no encontrado. Ejecuta procesos:create-current.");

                continue;
            }

            $fullPath = Storage::path($t['path']);

            if (! is_file($fullPath)) {
                $this->warn("PDF no encontrado en storage/app/{$t['path']} — saltando {$t['label']}.");

                continue;
            }

            $this->info("Importando {$t['label']} desde {$t['path']} (proceso #{$proceso->id})…");
            $exit = Artisan::call('vacantes:import-pdf', [
                'path' => $fullPath,
                'proceso_id' => $proceso->id,
                '--format' => 'suprimidos',
            ]);
            $this->line(trim(Artisan::output()));

            if ($exit === self::SUCCESS) {
                $proceso->update(['estado' => 'publicado']);
                $anyImported = true;
                $this->reportPerEspecialidad($proceso);
            }
        }

        if (! $anyImported) {
            $this->warn('No se importó ningún PDF. Coloca los ficheros en storage/app/pdfs/gva/ y reintenta.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveProceso(string $body): ?Proceso
    {
        $colectivo = Colectivo::where('code', 'SUPRIMIDO')->where('body', $body)->first();

        if (! $colectivo) {
            return null;
        }

        return Proceso::where('colectivo_id', $colectivo->id)
            ->where('curso', '2026-2027')
            ->first();
    }

    private function reportPerEspecialidad(Proceso $proceso): void
    {
        $rows = Vacancy::query()
            ->where('proceso_id', $proceso->id)
            ->selectRaw('specialty_id, count(*) as total')
            ->groupBy('specialty_id')
            ->with('specialty:id,name,code')
            ->get();

        $this->line("  Vacantes por especialidad (proceso #{$proceso->id}):");
        foreach ($rows as $row) {
            $name = $row->specialty?->name ?? ('#'.$row->specialty_id);
            $this->line("   - {$name}: {$row->total}");
        }
    }
}
