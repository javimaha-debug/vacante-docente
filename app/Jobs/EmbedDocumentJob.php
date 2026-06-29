<?php

namespace App\Jobs;

use App\Models\DocumentChunk;
use App\Models\UserDocument;
use App\Services\EmbeddingService;
use App\Support\Vector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Step 4 of the RAG pipeline: embed each chunk with Voyage AI (1024-dim) and
 * store the vector, then mark the document ready. Voyage is called in batches
 * of up to 128 chunks.
 */
class EmbedDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public readonly int $documentId) {}

    public function handle(EmbeddingService $embeddings): void
    {
        $document = UserDocument::find($this->documentId);
        if (! $document) {
            return;
        }

        $chunks = DocumentChunk::where('user_document_id', $document->id)
            ->orderBy('chunk_index')->get();

        if ($chunks->isEmpty()) {
            $document->forceFill(['processing_status' => 'ready'])->save();

            return;
        }

        try {
            $batchSize = (int) config('ai.voyage.batch', 128);
            $isPg = Vector::enabled();

            foreach ($chunks->chunk($batchSize) as $batch) {
                $vectors = $embeddings->embed($batch->pluck('content')->all(), 'document');

                foreach ($batch->values() as $i => $chunk) {
                    $vector = $vectors[$i] ?? null;
                    if (! $vector) {
                        continue;
                    }
                    $literal = EmbeddingService::toVectorLiteral($vector);
                    if ($isPg) {
                        DB::statement(
                            'UPDATE document_chunks SET embedding = ?::vector WHERE id = ?',
                            [$literal, $chunk->id],
                        );
                    } else {
                        // SQLite fallback: store the JSON array as text.
                        $chunk->forceFill(['embedding' => $literal])->save();
                    }
                }
            }

            $document->forceFill(['processing_status' => 'ready'])->save();
        } catch (\Throwable $e) {
            Log::warning('EmbedDocumentJob failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);
            $document->forceFill(['processing_status' => 'failed'])->save();
        }
    }
}
