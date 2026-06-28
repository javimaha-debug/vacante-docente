<?php

namespace App\Console\Commands;

use App\Jobs\MonitorGvaJob;
use App\Models\GvaNoticia;
use Illuminate\Console\Command;

class RunGvaMonitor extends Command
{
    protected $signature = 'gva:monitor';

    protected $description = 'Ejecuta el monitor de la GVA (RSS + páginas de adjudicaciones/resolución) y auto-importa los listados detectados.';

    public function handle(): int
    {
        $before = GvaNoticia::count();

        // Run synchronously so it works without a queue worker (the scheduler
        // calls this command directly).
        (new MonitorGvaJob())->handle();

        $after = GvaNoticia::count();

        $this->info('Monitor GVA ejecutado.');
        $this->line('Noticias nuevas detectadas: '.($after - $before));
        $this->line('Total noticias: '.$after);
        $this->line('Pendientes de importar: '.GvaNoticia::where('tipo', 'PDF')->whereNull('import_estado')->count());
        $this->line('Importadas (ok): '.GvaNoticia::where('import_estado', 'ok')->count());
        $this->line('Con error: '.GvaNoticia::where('import_estado', 'error')->count());
        $this->line('Sin proceso asociado: '.GvaNoticia::where('import_estado', 'sin_proceso')->count());

        return self::SUCCESS;
    }
}
