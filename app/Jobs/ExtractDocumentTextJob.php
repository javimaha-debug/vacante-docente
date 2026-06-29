<?php

namespace App\Jobs;

use App\Models\UserDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder for Sprint B: will extract the document's text for the AI
 * assistant (embeddings / search). For now it only records that extraction was
 * requested so the pipeline is wired end-to-end.
 */
class ExtractDocumentTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $documentId) {}

    public function handle(): void
    {
        $document = UserDocument::find($this->documentId);
        if (! $document) {
            return;
        }

        // Sprint B will OCR/parse and store extracted text + embeddings here.
        Log::info('ExtractDocumentTextJob: queued (placeholder)', ['document_id' => $document->id]);
    }
}
