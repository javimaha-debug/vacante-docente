<?php

namespace App\Console\Commands;

use App\Models\AdjudicacionContinua;
use App\Models\User;
use App\Notifications\AdjudicacionContinuaAsignada;
use App\Support\NameMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ImportAdjudicacionContinua extends Command
{
    protected $signature = 'adjudicaciones:import-continua {path : Ruta o URL del PDF de la tanda (YYMMDD_lis_sec.pdf)}
                            {--fecha= : Fecha de la tanda YYYY-MM-DD (si no se detecta del PDF)}
                            {--cuerpo= : SECUNDARIA|MAESTROS (si no se detecta del nombre)}
                            {--notify : Avisa a los usuarios adjudicados en esta tanda}
                            {--dry-run : Analiza sin escribir}';

    protected $description = 'Importa una tanda de adjudicaciones contínues (semanales), conservando el histórico por fecha.';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $source = $path;

        if (preg_match('#^https?://#i', $path)) {
            $path = $this->downloadPdf($path);
            if ($path === null) {
                return self::FAILURE;
            }
        }
        if (! is_file($path)) {
            $this->error("PDF no encontrado: {$path}");

            return self::FAILURE;
        }

        $text = $this->extractText($path);
        if ($text === null) {
            return self::FAILURE;
        }

        $fecha = $this->resolveFecha($text, $source, $this->option('fecha'));
        $cuerpo = $this->resolveCuerpo($source, $this->option('cuerpo'));

        if (! $fecha) {
            $this->error('No se pudo determinar la fecha de la tanda. Usa --fecha=YYYY-MM-DD.');

            return self::FAILURE;
        }
        if (! $cuerpo) {
            $this->error('No se pudo determinar el cuerpo. Usa --cuerpo=SECUNDARIA|MAESTROS.');

            return self::FAILURE;
        }

        $curso = $this->cursoFromFecha($fecha);

        // Reuse the start-of-course adjudication parser (same layout).
        $rows = (new ImportParticipantesPdf)->parseText($text);
        $this->info('Tanda '.$fecha->toDateString()." ({$cuerpo}, curso {$curso}) — ".count($rows).' filas, '
            .collect($rows)->pluck('nombre_gva')->unique()->count().' personas.');

        if ($this->option('dry-run')) {
            $this->line('Dry run — no se escribe nada.');

            return self::SUCCESS;
        }

        // Map nombre_gva → user_id so each row can be linked to a registered user.
        $usersByName = User::whereNotNull('nombre_gva')->get(['id', 'nombre_gva'])
            ->keyBy(fn ($u) => mb_strtolower(trim($u->nombre_gva)));

        $now = now();
        $payload = array_map(fn ($r) => [
            'curso' => $curso,
            'fecha' => $fecha->toDateString(),
            'cuerpo' => $cuerpo,
            'nombre_gva' => $r['nombre_gva'],
            // Bulk insert() bypasses model events, so fold here.
            'nombre_normalizado' => NameMatch::fold($r['nombre_gva'] ?? ''),
            'especialidad_codigo' => $r['especialidad_codigo'] ?? null,
            'posicion' => $r['posicion'] ?? null,
            'estado' => $r['estado'] ?? null,
            'lloc_adjudicado' => $r['lloc_adjudicado'] ?? null,
            'centro_codigo' => $r['centro_codigo'] ?? null,
            'centro_nombre' => $r['centro_nombre'] ?? null,
            'localitat' => $r['localitat'] ?? null,
            'jornada' => $r['jornada'] ?? null,
            'user_id' => $usersByName->get(mb_strtolower(trim((string) $r['nombre_gva'])))?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        DB::transaction(function () use ($fecha, $cuerpo, $payload) {
            // Re-runnable: replace this tanda (same fecha+cuerpo) and re-insert.
            AdjudicacionContinua::whereDate('fecha', $fecha->toDateString())->where('cuerpo', $cuerpo)->delete();
            foreach (array_chunk($payload, 500) as $chunk) {
                AdjudicacionContinua::insert($chunk);
            }
        });

        $linked = collect($payload)->whereNotNull('user_id')->count();
        $adj = collect($payload)->where('estado', 'Adjudicat')->count();
        $this->info('Importadas '.count($payload)." filas; {$adj} adjudicades; {$linked} enllaçades a usuaris.");

        if ($this->option('notify')) {
            $avisados = $this->notifyAdjudicados($fecha, $cuerpo);
            $this->info("Avisados {$avisados} usuarios adjudicados.");
        }

        return self::SUCCESS;
    }

    /**
     * Notify the registered users adjudicated in a freshly imported tanda.
     */
    private function notifyAdjudicados(Carbon $fecha, string $cuerpo): int
    {
        $rows = AdjudicacionContinua::whereDate('fecha', $fecha->toDateString())
            ->where('cuerpo', $cuerpo)
            ->where('estado', 'Adjudicat')
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            if (! $row->user) {
                continue;
            }
            $row->user->notify(new AdjudicacionContinuaAsignada(
                $fecha->toDateString(),
                $row->centro_nombre,
                $row->localitat,
                $row->especialidad_codigo,
                $row->jornada,
            ));
            $count++;
        }

        return $count;
    }

    /**
     * Tanda date: from the title "DIA dd/mm/aaaa" or the YYMMDD filename prefix.
     */
    private function resolveFecha(string $text, string $source, ?string $override = null): ?Carbon
    {
        if ($override) {
            try {
                return Carbon::parse($override)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }
        if (preg_match('#DIA\s+(\d{2})/(\d{2})/(\d{4})#u', $text, $m)) {
            return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->startOfDay();
        }
        if (preg_match('#(\d{2})(\d{2})(\d{2})_lis#', basename($source), $m)) {
            return Carbon::createFromDate(2000 + (int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
        }

        return null;
    }

    private function resolveCuerpo(string $source, ?string $override = null): ?string
    {
        if ($override) {
            return strtoupper($override);
        }
        $name = mb_strtolower(basename($source));
        if (str_contains($name, '_mae')) {
            return 'MAESTROS';
        }
        if (str_contains($name, '_sec')) {
            return 'SECUNDARIA';
        }

        return null;
    }

    private function cursoFromFecha(Carbon $fecha): string
    {
        $y = (int) $fecha->year;

        return $fecha->month >= 9 ? $y.'-'.($y + 1) : ($y - 1).'-'.$y;
    }

    private function downloadPdf(string $url): ?string
    {
        $this->info("Descargando {$url} …");
        try {
            $response = Http::timeout(120)->get($url);
        } catch (\Throwable $e) {
            $this->error('No se pudo descargar: '.$e->getMessage());

            return null;
        }
        if (! $response->successful()) {
            $this->error('La descarga devolvió HTTP '.$response->status());

            return null;
        }
        $name = basename(parse_url($url, PHP_URL_PATH) ?: 'tanda.pdf');
        if (! str_ends_with(mb_strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }
        $relative = 'pdfs/gva/continua/'.$name;
        Storage::disk('local')->put($relative, $response->body());

        return Storage::disk('local')->path($relative);
    }

    private function extractText(string $path): ?string
    {
        $process = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);
        $process->setTimeout(180);
        try {
            $process->run();
        } catch (\Throwable $e) {
            $this->error('No se pudo ejecutar pdftotext: '.$e->getMessage());

            return null;
        }
        if (! $process->isSuccessful()) {
            $this->error('pdftotext falló: '.trim($process->getErrorOutput()));

            return null;
        }

        return $process->getOutput();
    }
}
