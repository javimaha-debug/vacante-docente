<?php

namespace App\Jobs;

use App\Models\UserDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Post-upload processing: derive page/word counts and a page-1 thumbnail, then
 * mark the document ready and hand off to text extraction (Sprint B).
 *
 * Everything that depends on optional system tools (pdfinfo / imagick) is
 * best-effort: a missing tool degrades the metadata, it never fails the job.
 */
class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public function __construct(public readonly int $documentId) {}

    public function handle(): void
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

            match ($document->type) {
                'pdf' => $this->processPdf($document, $disk),
                'image' => $document->forceFill(['page_count' => 1])->save(),
                'word' => $this->processWord($document, $disk),
                default => null,
            };

            $document->forceFill(['processing_status' => 'ready'])->save();
        } catch (\Throwable $e) {
            Log::warning('ProcessDocumentJob failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);
            $document->forceFill(['processing_status' => 'failed'])->save();

            return;
        }

        ExtractDocumentTextJob::dispatch($document->id);
    }

    private function processPdf(UserDocument $document, $disk): void
    {
        // Pull the bytes to a local temp file so CLI tools can read them
        // (works whether the disk is local or remote/Spaces).
        $tmp = tempnam(sys_get_temp_dir(), 'doc').'.pdf';
        file_put_contents($tmp, $disk->get($document->disk_path));

        try {
            $pages = $this->pdfPageCount($tmp);
            if ($pages !== null) {
                $document->forceFill(['page_count' => $pages])->save();
            }

            $thumb = $this->pdfThumbnail($tmp, $document);
            if ($thumb !== null) {
                $document->forceFill(['thumbnail_path' => $thumb])->save();
            }
        } finally {
            @unlink($tmp);
        }
    }

    private function pdfPageCount(string $path): ?int
    {
        try {
            $process = new Process(['pdfinfo', $path]);
            $process->setTimeout(30);
            $process->run();
            if ($process->isSuccessful() && preg_match('/^Pages:\s+(\d+)/m', $process->getOutput(), $m)) {
                return (int) $m[1];
            }
        } catch (\Throwable $e) {
            Log::info('pdfinfo unavailable', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /** Render page 1 to a JPEG thumbnail with Imagick, store it, return its path. */
    private function pdfThumbnail(string $path, UserDocument $document): ?string
    {
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $imagick = new \Imagick();
            $imagick->setResolution(96, 96);
            $imagick->readImage($path.'[0]');
            $imagick->setImageFormat('jpeg');
            $imagick->thumbnailImage(400, 0);
            $blob = $imagick->getImageBlob();
            $imagick->clear();

            $thumbPath = 'users/'.$document->user_id.'/thumbs/'.$document->id.'.jpg';
            Storage::disk(config('documents.disk'))->put($thumbPath, $blob);

            return $thumbPath;
        } catch (\Throwable $e) {
            Log::info('Imagick thumbnail failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function processWord(UserDocument $document, $disk): void
    {
        // Full Word page/word counts arrive with the Sprint B parser; for now we
        // leave counts null and just mark the document ready.
    }
}
