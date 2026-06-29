<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Voyage AI embeddings. Returns 1024-dim vectors used for RAG retrieval.
 * Counts API calls so AiUsage can track Voyage spend.
 */
class EmbeddingService
{
    public int $lastCallCount = 0;

    /**
     * Embed one or more texts. `inputType` is 'document' for stored chunks and
     * 'query' for search queries (Voyage optimises each differently).
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>  one vector per input text
     */
    public function embed(array $texts, string $inputType = 'document'): array
    {
        $texts = array_values(array_filter($texts, fn ($t) => trim((string) $t) !== ''));
        if (empty($texts)) {
            return [];
        }

        $batchSize = (int) config('ai.voyage.batch', 128);
        $out = [];
        $this->lastCallCount = 0;

        foreach (array_chunk($texts, $batchSize) as $batch) {
            $response = Http::withToken((string) config('ai.voyage.api_key'))
                ->timeout(60)
                ->post((string) config('ai.voyage.endpoint'), [
                    'model' => config('ai.voyage.model'),
                    'input' => $batch,
                    'input_type' => $inputType,
                    'output_dimension' => (int) config('ai.voyage.dimensions'),
                ])
                ->throw()
                ->json();

            $this->lastCallCount++;

            // Voyage returns data sorted by index.
            $vectors = collect($response['data'] ?? [])
                ->sortBy('index')
                ->map(fn ($d) => array_map('floatval', $d['embedding'] ?? []))
                ->values()->all();

            $out = array_merge($out, $vectors);
        }

        return $out;
    }

    /** Convenience: embed a single query string → one vector. */
    public function embedQuery(string $text): array
    {
        return $this->embed([$text], 'query')[0] ?? [];
    }

    /** Format a float vector as a pgvector literal "[v1,v2,...]". */
    public static function toVectorLiteral(array $vector): string
    {
        return '['.implode(',', array_map(fn ($v) => rtrim(rtrim(sprintf('%.8f', $v), '0'), '.'), $vector)).']';
    }
}
