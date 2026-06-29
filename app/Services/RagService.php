<?php

namespace App\Services;

use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;

/**
 * Retrieval for RAG: embed the query, find the nearest document chunks (cosine
 * similarity) and format them as cited context for Claude.
 *
 * Uses pgvector's `<=>` operator on PostgreSQL; on SQLite (tests) it falls back
 * to computing cosine similarity in PHP over the stored JSON vectors.
 */
class RagService
{
    public function __construct(private readonly EmbeddingService $embeddings) {}

    /**
     * @param  array<int, int>|null  $documentIds  restrict to these documents
     * @return array<int, array{id:int, content:string, page_number:?int, document_name:string, similarity:float}>
     */
    public function search(string $query, int $userId, ?array $documentIds = null, int $limit = 6): array
    {
        $queryVector = $this->embeddings->embedQuery($query);
        if (empty($queryVector)) {
            return [];
        }

        $threshold = (float) config('ai.rag.similarity_threshold', 0.7);

        return DB::getDriverName() === 'pgsql'
            ? $this->searchPg($queryVector, $userId, $documentIds, $limit, $threshold)
            : $this->searchFallback($queryVector, $userId, $documentIds, $limit, $threshold);
    }

    private function searchPg(array $queryVector, int $userId, ?array $documentIds, int $limit, float $threshold): array
    {
        $literal = EmbeddingService::toVectorLiteral($queryVector);
        $docFilter = '';
        if ($documentIds) {
            $docFilter = ' AND dc.user_document_id IN ('.implode(',', array_map('intval', $documentIds)).')';
        }

        $rows = DB::select(
            'SELECT dc.id, dc.content, dc.page_number, ud.name as document_name,
                    1 - (dc.embedding <=> ?::vector) as similarity
             FROM document_chunks dc
             JOIN user_documents ud ON dc.user_document_id = ud.id
             WHERE dc.user_id = ? AND 1 - (dc.embedding <=> ?::vector) > ?'.$docFilter.'
             ORDER BY dc.embedding <=> ?::vector
             LIMIT '.(int) $limit,
            // Placeholder order: SELECT similarity, user_id, WHERE similarity, threshold, ORDER BY.
            [$literal, $userId, $literal, $threshold, $literal],
        );

        return array_map(fn ($r) => [
            'id' => (int) $r->id,
            'content' => $r->content,
            'page_number' => $r->page_number !== null ? (int) $r->page_number : null,
            'document_name' => $r->document_name,
            'similarity' => round((float) $r->similarity, 4),
        ], $rows);
    }

    private function searchFallback(array $queryVector, int $userId, ?array $documentIds, int $limit, float $threshold): array
    {
        $chunks = DocumentChunk::query()
            ->where('user_id', $userId)
            ->whereNotNull('embedding')
            ->when($documentIds, fn ($q) => $q->whereIn('user_document_id', $documentIds))
            ->with('document:id,name')
            ->get();

        $scored = [];
        foreach ($chunks as $chunk) {
            $sim = self::cosine($queryVector, $chunk->embeddingVector());
            if ($sim > $threshold) {
                $scored[] = [
                    'id' => $chunk->id,
                    'content' => $chunk->content,
                    'page_number' => $chunk->page_number,
                    'document_name' => $chunk->document?->name ?? '',
                    'similarity' => round($sim, 4),
                ];
            }
        }

        usort($scored, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Format retrieved chunks as cited context for the prompt.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     */
    public function buildContext(array $chunks): string
    {
        return collect($chunks)->map(function ($c) {
            $page = $c['page_number'] ? ", página {$c['page_number']}" : '';

            return "[Fuente: \"{$c['document_name']}\"{$page}]\n{$c['content']}";
        })->implode("\n\n");
    }

    /** @return float cosine similarity in [-1, 1] (0 when either vector is empty) */
    public static function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = $na = $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na == 0.0 || $nb == 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
