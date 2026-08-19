<?php

namespace App\Console\Commands;

use App\Models\TestRazonamiento;
use Illuminate\Console\Command;

class ImportEpsoExpanded extends Command
{
    protected $signature = 'epso:import-expanded {--file=}';
    protected $description = 'Importa ejercicios EPSO expandidos (AST/AD/SJT) desde JSON';

    public function handle(): void
    {
        $file = $this->option('file') ?? storage_path('app/epso/epso_exercises_expanded.json');

        if (!file_exists($file)) {
            $this->error("Archivo no encontrado: {$file}");
            return;
        }

        $exercises = json_decode(file_get_contents($file), true);
        if (!$exercises) {
            $this->error('JSON inválido o vacío.');
            return;
        }

        $this->info("Importando " . count($exercises) . " ejercicios EPSO expandidos...");

        $imported = 0;
        $skipped = 0;

        foreach ($exercises as $ex) {
            $exists = TestRazonamiento::where('tipo', $ex['tipo'])
                ->where('nivel', $ex['nivel'] ?? 'ambos')
                ->where('pregunta', $ex['pregunta'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            TestRazonamiento::create([
                'tipo'                     => $ex['tipo'],
                'nivel'                    => $ex['nivel'] ?? 'ambos',
                'pregunta'                 => $ex['pregunta'],
                'opciones'                 => $ex['opciones'],
                'respuesta_correcta'       => $ex['respuesta_correcta'],
                'tiempo_esperado_segundos' => $ex['tiempo_esperado_segundos'] ?? 90,
                'explicacion'              => $ex['explicacion'] ?? '',
                'dificultad'               => $ex['dificultad'] ?? 2,
                'tipo_error'               => $ex['tipo_error'] ?? null,
            ]);

            $imported++;
        }

        $this->line("✓ {$imported} importados, {$skipped} ya existían");
        $this->info('✅ Importación completada');

        $this->table(
            ['Tipo', 'Nivel', 'Total'],
            TestRazonamiento::selectRaw('tipo, nivel, COUNT(*) as total')
                ->groupBy('tipo', 'nivel')
                ->orderBy('tipo')->orderBy('nivel')
                ->get()
                ->map(fn($r) => [$r->tipo, $r->nivel, $r->total])
                ->toArray()
        );
    }
}
