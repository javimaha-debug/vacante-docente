<?php

namespace App\Jobs;

use App\Models\SyncState;
use App\Models\TemaOficial;
use App\Models\TemarioOficial;
use App\Services\AnthropicService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Enriches every tema of an official temario with an AI-generated study esquema
 * + bibliography. Processes temas in batches of 10 to stay under rate limits.
 * Only fills temas that are not yet enriched (idempotent / resumable).
 */
class GenerateTemarioEnrichmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public readonly int $temarioId,
        public readonly bool $force = false,
    ) {}

    public function handle(AnthropicService $anthropic): void
    {
        $temario = TemarioOficial::find($this->temarioId);
        if (! $temario) {
            return;
        }

        $query = TemaOficial::where('temario_id', $temario->id)->orderBy('numero');
        if (! $this->force) {
            $query->whereNull('generated_at');
        }

        $tokensIn = 0;
        $tokensOut = 0;
        $done = 0;

        foreach ($query->get()->chunk(10) as $batch) {
            foreach ($batch as $tema) {
                try {
                    [$in, $out] = $this->enrichTema($anthropic, $temario, $tema);
                    $tokensIn += $in;
                    $tokensOut += $out;
                    $done++;
                } catch (\Throwable $e) {
                    Log::warning('TemarioEnrichment: tema failed', ['tema_id' => $tema->id, 'error' => $e->getMessage()]);
                }
            }
            Log::info('TemarioEnrichment batch done', [
                'temario_id' => $temario->id, 'tokens_input' => $tokensIn, 'tokens_output' => $tokensOut,
            ]);
        }

        SyncState::record('temario_enrichment', [
            'temario_id' => $temario->id,
            'temas_enriquecidos' => $done,
            'tokens_input' => $tokensIn,
            'tokens_output' => $tokensOut,
            'coste_estimado_usd' => $this->estimateCost($tokensIn, $tokensOut),
        ]);
    }

    /**
     * @return array{0:int, 1:int} tokens [input, output]
     */
    private function enrichTema(AnthropicService $anthropic, TemarioOficial $temario, TemaOficial $tema): array
    {
        $esquemaPrompt = <<<PROMPT
        Eres un experto preparador de oposiciones docentes en España.
        Para el siguiente tema oficial del temario de {$temario->especialidad_nombre} ({$temario->cuerpo}),
        genera un esquema de estudio estructurado.

        Tema {$tema->numero}: {$tema->titulo}

        Responde ÚNICAMENTE con JSON válido:
        {
          "esquema": [ { "punto": "I. Introducción", "subpuntos": ["1.1 Concepto", "1.2 Marco normativo"] } ],
          "keywords": ["término1", "término2"],
          "tiempo_estimado_minutos": 120
        }
        PROMPT;

        $esquemaRes = $anthropic->chat([['role' => 'user', 'content' => $esquemaPrompt]], null, 1500);
        $esquemaJson = $this->parseJson($esquemaRes['text']);

        $bibPrompt = <<<PROMPT
        Para el tema {$tema->numero} ({$tema->titulo}) de las oposiciones de
        {$temario->especialidad_nombre} en España, sugiere 4-6 referencias bibliográficas
        reales y verificables. Incluye normativa legal relevante.

        Responde ÚNICAMENTE con JSON válido:
        [ { "tipo": "libro|ley|decreto|articulo", "titulo": "...", "autor": "...", "año": 2023, "editorial": "...", "url": "..." } ]
        PROMPT;

        $bibRes = $anthropic->chat([['role' => 'user', 'content' => $bibPrompt]], null, 1500);
        $bibJson = $this->parseJson($bibRes['text']);

        $esquema = is_array($esquemaJson['esquema'] ?? null) ? $esquemaJson['esquema'] : null;
        $keywords = is_array($esquemaJson['keywords'] ?? null) ? $esquemaJson['keywords'] : null;
        $tiempo = isset($esquemaJson['tiempo_estimado_minutos']) ? (int) $esquemaJson['tiempo_estimado_minutos'] : null;
        $bibliografia = is_array($bibJson) ? (array_is_list($bibJson) ? $bibJson : ($bibJson['bibliografia'] ?? null)) : null;

        $tema->forceFill([
            'esquema' => $esquema,
            'keywords' => $keywords,
            'tiempo_estimado_minutos' => $tiempo,
            'bibliografia' => $bibliografia,
            'generated_at' => now(),
        ])->save();

        return [
            $esquemaRes['tokens_input'] + $bibRes['tokens_input'],
            $esquemaRes['tokens_output'] + $bibRes['tokens_output'],
        ];
    }

    /**
     * Pull the first JSON value out of a model response (tolerates code fences
     * and surrounding prose).
     *
     * @return array<mixed>
     */
    private function parseJson(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?|```$/m', '', $text);
        $decoded = json_decode(trim($text), true);
        if (is_array($decoded)) {
            return $decoded;
        }
        // Fall back to the first {...} or [...] block.
        if (preg_match('/(\{.*\}|\[.*\])/s', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /** Rough Sonnet cost estimate (USD): $3/M input, $15/M output. */
    private function estimateCost(int $tokensIn, int $tokensOut): float
    {
        return round(($tokensIn / 1_000_000) * 3 + ($tokensOut / 1_000_000) * 15, 4);
    }
}
