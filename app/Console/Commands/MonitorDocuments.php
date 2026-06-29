<?php

namespace App\Console\Commands;

use App\Models\MonitoredSource;
use App\Models\User;
use App\Notifications\DocumentosDetectados;
use App\Services\DocumentMonitorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class MonitorDocuments extends Command
{
    protected $signature = 'documents:monitor {--source= : Only scan this monitored source id}';

    protected $description = 'Scan monitored sources for new official documents and suggest calendar events.';

    public function handle(DocumentMonitorService $service): int
    {
        $sources = MonitoredSource::query()
            ->where('active', true)
            ->when($this->option('source'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($sources->isEmpty()) {
            $this->warn('No hay fuentes activas que comprobar.');

            return self::SUCCESS;
        }

        $totalNuevos = 0;
        $totalEventos = 0;
        $titulos = [];

        foreach ($sources as $source) {
            $r = $service->scan($source);
            $totalNuevos += $r['nuevos'];
            $totalEventos += $r['eventos'];
            $titulos = array_merge($titulos, $r['titulos']);

            $line = "· {$source->name}: {$r['nuevos']} nuevo(s), {$r['eventos']} evento(s)";
            if ($r['error']) {
                $line .= " — error: {$r['error']}";
                $this->warn($line);
            } else {
                $this->line($line);
            }
        }

        if ($totalNuevos > 0) {
            $this->notifySuperadmins($totalNuevos, $titulos, $totalEventos);
        }

        $this->info(sprintf(
            'Fuentes comprobadas: %d · documentos nuevos: %d · eventos sugeridos: %d',
            $sources->count(), $totalNuevos, $totalEventos,
        ));
        Log::info('MonitorDocuments done', [
            'sources' => $sources->count(),
            'nuevos' => $totalNuevos,
            'eventos' => $totalEventos,
        ]);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $titulos
     */
    private function notifySuperadmins(int $nuevos, array $titulos, int $eventos): void
    {
        $admins = User::query()
            ->where('is_admin', true)
            ->orWhere('role', 'superadmin')
            ->orWhere('id', 1)
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new DocumentosDetectados($nuevos, $titulos, $eventos));
        }
    }
}
