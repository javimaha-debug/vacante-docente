<?php

namespace App\Jobs;

use App\Models\DocumentChunk;
use App\Models\UserDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Step 3 of the RAG pipeline: split the extracted text into ~400-token chunks
 * with ~50-token overlap, one row per chunk (embedding filled in by the next
 * job). Re-runnable: replaces this document's existing chunks.
 */
class ChunkDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $documentId) {}

    public function handle(): void
    {
        $document = UserDocument::find($this->documentId);
        if (! $document) {
            return;
        }

        $disk = Storage::disk(config('documents.disk'));
        $path = ExtractDocumentTextJob::extractedPath($document);

        try {
            $pages = $disk->exists($path) ? (json_decode($disk->get($path), true) ?: []) : [];
        } catch (\Throwable $e) {
            $pages = [];
        }

        $chunkTokens = (int) config('ai.rag.chunk_tokens', 400);
        $overlapTokens = (int) config('ai.rag.chunk_overlap', 50);
        $minTokens = (int) config('ai.rag.min_chunk_tokens', 50);

        DocumentChunk::where('user_document_id', $document->id)->delete();

        $index = 0;
        $totalWords = 0;
        foreach ($pages as $page) {
            foreach ($this->chunkText((string) $page['text'], $chunkTokens, $overlapTokens) as $content) {
                $tokenCount = self::estimateTokens($content);
                // Keep small chunks only when the document yielded nothing else.
                if ($tokenCount < $minTokens && $index > 0) {
                    continue;
                }
                DocumentChunk::create([
                    'user_document_id' => $document->id,
                    'user_id' => $document->user_id,
                    'chunk_index' => $index++,
                    'page_number' => $page['page'] ?? null,
                    'content' => $content,
                    'token_count' => $tokenCount,
                ]);
                $totalWords += str_word_count($content);
            }
        }

        $document->forceFill(['word_count' => $totalWords])->save();

        if ($index === 0) {
            // Nothing chunkable (e.g. an image with no readable text): still ready.
            $document->forceFill(['processing_status' => 'ready'])->save();
            Log::info('ChunkDocumentJob: no chunks produced', ['document_id' => $document->id]);

            return;
        }

        EmbedDocumentJob::dispatch($document->id);
    }

    /**
     * Word-windowed chunking with overlap. Token counts are estimated
     * (~1.33 words/token); paragraph boundaries are kept where a window aligns.
     *
     * @return array<int, string>
     */
    public function chunkText(string $text, int $chunkTokens, int $overlapTokens): array
    {
        $text = trim(preg_replace('/[ \t]+/', ' ', $text));
        if ($text === '') {
            return [];
        }

        $words = preg_split('/\s+/', $text);
        $perChunk = max(1, (int) round($chunkTokens * 1.33));
        $overlap = max(0, (int) round($overlapTokens * 1.33));
        $step = max(1, $perChunk - $overlap);

        $chunks = [];
        for ($start = 0; $start < count($words); $start += $step) {
            $slice = array_slice($words, $start, $perChunk);
            if (empty($slice)) {
                break;
            }
            $chunks[] = implode(' ', $slice);
            if ($start + $perChunk >= count($words)) {
                break;
            }
        }

        return $chunks;
    }

    /** Rough token estimate (~4 chars/token). */
    public static function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }
}
