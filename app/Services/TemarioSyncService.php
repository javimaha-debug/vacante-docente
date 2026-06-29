<?php

namespace App\Services;

use App\Jobs\GenerateTemarioEnrichmentJob;
use App\Models\Specialty;
use App\Models\TemaOficial;
use App\Models\TemarioOficial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persists parsed BOE temarios into temarios_oficiales / temas_oficiales and
 * dispatches AI enrichment. The official temario is NATIONAL (one per cuerpo +
 * especialidad), so re-syncing updates in place.
 */
class TemarioSyncService
{
    /**
     * @param  array<int, array{especialidad_nombre:string, especialidad_code?:?string, temas:array<int, array{numero:int, titulo:string}>}>  $especialidades
     * @param  array{source_url?:?string, source_order?:?string, published_at?:?string}  $meta
     * @return array{temarios:int, temas:int}
     */
    public function ingestParsed(string $cuerpo, array $especialidades, array $meta = [], bool $enrich = true): array
    {
        $temarioCount = 0;
        $temaCount = 0;

        foreach ($especialidades as $esp) {
            if (empty($esp['temas'])) {
                continue;
            }

            $nombre = $esp['especialidad_nombre'];
            $code = $esp['especialidad_code'] ?? $this->resolveCode($nombre, $cuerpo);

            $temario = DB::transaction(function () use ($cuerpo, $nombre, $code, $esp, $meta) {
                $temario = TemarioOficial::updateOrCreate(
                    ['cuerpo' => $cuerpo, 'especialidad_code' => $code, 'comunidad_autonoma' => 'nacional'],
                    [
                        'especialidad_nombre' => $nombre,
                        'source_url' => $meta['source_url'] ?? null,
                        'source_order' => $meta['source_order'] ?? null,
                        'published_at' => $meta['published_at'] ?? null,
                        'total_temas' => count($esp['temas']),
                        'last_synced_at' => now(),
                    ],
                );

                foreach ($esp['temas'] as $t) {
                    // Preserve any existing AI enrichment when the title is unchanged.
                    $existing = TemaOficial::where('temario_id', $temario->id)->where('numero', $t['numero'])->first();
                    if ($existing && $this->normalize($existing->titulo) === $this->normalize($t['titulo'])) {
                        continue;
                    }
                    TemaOficial::updateOrCreate(
                        ['temario_id' => $temario->id, 'numero' => $t['numero']],
                        ['titulo' => $t['titulo']],
                    );
                }

                return $temario;
            });

            $temarioCount++;
            $temaCount += count($esp['temas']);

            if ($enrich) {
                GenerateTemarioEnrichmentJob::dispatch($temario->id);
            }
        }

        return ['temarios' => $temarioCount, 'temas' => $temaCount];
    }

    /**
     * Resolve a Specialty code from a BOE specialty name; falls back to a slug.
     */
    public function resolveCode(string $nombre, string $cuerpo): string
    {
        $needle = $this->normalize($nombre);

        $match = Specialty::query()
            ->when($cuerpo !== 'otros', fn ($q) => $q->where('education_level', $cuerpo))
            ->get(['code', 'name'])
            ->first(fn ($s) => $this->normalize($s->name) === $needle);

        if ($match) {
            return (string) $match->code;
        }

        // Partial match (the BOE name may include extra qualifiers).
        $partial = Specialty::query()
            ->when($cuerpo !== 'otros', fn ($q) => $q->where('education_level', $cuerpo))
            ->get(['code', 'name'])
            ->first(fn ($s) => str_contains($needle, $this->normalize($s->name)) || str_contains($this->normalize($s->name), $needle));

        return $partial ? (string) $partial->code : 'BOE-'.Str::slug(Str::limit($nombre, 40, ''));
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'ä' => 'a', 'è' => 'e', 'é' => 'e', 'ë' => 'e',
            'í' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ö' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', ' ', $value);
    }
}
