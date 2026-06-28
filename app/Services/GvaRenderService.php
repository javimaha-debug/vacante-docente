<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Renders the GVA's JavaScript pages with a headless Chromium (via the Node
 * script scripts/gva-render.mjs) and returns the PDF links found.
 *
 * Degrades gracefully: if rendering is disabled or the browser/node isn't
 * available, returns null so the caller can fall back to static scraping.
 */
class GvaRenderService
{
    /**
     * @return array<int, array{titulo: string, url: string}>|null
     */
    public function pdfLinks(string ...$urls): ?array
    {
        if (! config('gva.render.enabled', true) || empty($urls)) {
            return null;
        }

        $node = (string) config('gva.render.node', 'node');
        $script = (string) config('gva.render.script');
        if (! is_file($script)) {
            Log::warning('GvaRenderService: render script not found', ['script' => $script]);

            return null;
        }

        $env = [];
        if ($chromium = config('gva.render.chromium')) {
            $env['GVA_CHROMIUM_PATH'] = $chromium;
        }

        $process = new Process(
            array_merge([$node, $script], array_values($urls)),
            base_path(),
            $env ?: null,
            null,
            (float) config('gva.render.timeout', 90),
        );

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::warning('GvaRenderService: process error', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $process->isSuccessful()) {
            Log::warning('GvaRenderService: render failed', ['stderr' => mb_substr($process->getErrorOutput(), 0, 500)]);

            return null;
        }

        $data = json_decode(trim($process->getOutput()), true);
        if (! is_array($data) || empty($data['ok'])) {
            Log::warning('GvaRenderService: bad render output', ['error' => $data['error'] ?? 'unparseable']);

            return null;
        }

        return array_map(
            fn ($l) => ['titulo' => (string) ($l['titulo'] ?? ''), 'url' => (string) ($l['url'] ?? '')],
            array_filter($data['links'] ?? [], fn ($l) => ! empty($l['url'])),
        );
    }
}
