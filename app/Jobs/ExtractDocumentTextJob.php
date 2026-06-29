<?php

namespace App\Jobs;

use App\Models\UserDocument;
use App\Services\AnthropicService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Step 2 of the RAG pipeline: extract the document's text page by page and
 * stash it (as JSON) for ChunkDocumentJob. PDFs via pdftotext, Word via the
 * docx XML, images via Claude Haiku Vision OCR.
 */
class ExtractDocumentTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public readonly int $documentId) {}

    /** Where the per-page extracted text is stored for the next job. */
    public static function extractedPath(UserDocument $document): string
    {
        return "users/{$document->user_id}/extracted/{$document->id}.json";
    }

    public function handle(AnthropicService $anthropic): void
    {
        $document = UserDocument::find($this->documentId);
        if (! $document) {
            return;
        }

        $document->forceFill(['processing_status' => 'processing'])->save();
        $disk = Storage::disk(config('documents.disk'));

        try {
            if (! $disk->exists($document->disk_path)) {
                throw new \RuntimeException('Archivo no encontrado en almacenamiento.');
            }
            $bytes = $disk->get($document->disk_path);

            $pages = match ($document->type) {
                'pdf' => $this->extractPdf($bytes),
                'word' => $this->extractWord($bytes),
                'image' => [$anthropic->ocrImage($bytes, $document->mime_type ?: 'image/jpeg')],
                default => [],
            };

            // Normalise to [{page, text}], dropping empty pages.
            $pageData = [];
            foreach ($pages as $i => $text) {
                $text = trim((string) $text);
                if ($text !== '') {
                    $pageData[] = ['page' => $i + 1, 'text' => $text];
                }
            }

            $disk->put(self::extractedPath($document), json_encode($pageData));
        } catch (\Throwable $e) {
            Log::warning('ExtractDocumentTextJob failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);
            $document->forceFill(['processing_status' => 'failed'])->save();

            return;
        }

        ChunkDocumentJob::dispatch($document->id);
    }

    /** @return array<int, string> one entry per page */
    private function extractPdf(string $bytes): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
        file_put_contents($tmp, $bytes);
        try {
            $process = new Process(['pdftotext', '-enc', 'UTF-8', $tmp, '-']);
            $process->setTimeout(120);
            $process->run();
            if (! $process->isSuccessful()) {
                return [];
            }
            // pdftotext separates pages with a form-feed (\f).
            return explode("\f", $process->getOutput());
        } finally {
            @unlink($tmp);
        }
    }

    /** Extract text from a .docx by reading its document.xml (no extra deps). */
    private function extractWord(string $bytes): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx').'.docx';
        file_put_contents($tmp, $bytes);
        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmp) !== true) {
                return [];
            }
            $xml = $zip->getFromName('word/document.xml') ?: '';
            $zip->close();
            if ($xml === '') {
                return [];
            }
            $xml = str_replace('</w:p>', "\n", $xml);
            $byPage = preg_split('/<w:br[^>]*w:type="page"[^>]*\/>/', $xml) ?: [$xml];

            return array_map(fn ($p) => trim(html_entity_decode(strip_tags($p))), $byPage);
        } finally {
            @unlink($tmp);
        }
    }
}
