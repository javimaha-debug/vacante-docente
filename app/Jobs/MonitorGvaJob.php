<?php

namespace App\Jobs;

use App\Models\GvaNoticia;
use App\Models\User;
use App\Notifications\ListadoImportadoAdmin;
use App\Services\GvaAutoImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class MonitorGvaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry transient failures with backoff; bound total runtime. */
    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 600;

    private const RSS_URL = 'https://dogv.gva.es/portal/rss/rss.xhtml';

    private const ADJUDICACIONES_URL = 'https://ceice.gva.es/va/web/rrhh-educacion/adjudicaciones';

    /** Weekly ("contínues") adjudication results page. */
    private const RESOLUCIO_URL = 'https://ceice.gva.es/va/web/rrhh-educacion/resolucion';

    /** Keywords (lower-case) that mark a notice as relevant to docentes. */
    public const KEYWORDS = [
        'adjudicació', 'adjudicacion', 'personal docent', 'interí', 'interino',
        'vacants', 'vacantes', 'borsa', 'bolsa',
    ];

    public function handle(): void
    {
        $rss = $this->fetchRssNoticias();
        $pdfs = $this->fetchPdfNoticias();
        $continua = $this->fetchHtmlPdfNoticias(self::RESOLUCIO_URL);

        $created = array_merge($this->persist($rss), $this->persist($pdfs), $this->persist($continua));

        Log::info('MonitorGvaJob: '.count($created).' new GVA notice(s) stored.');

        if (config('gva.auto_import', true)) {
            // Drive auto-import off notice STATE (not the in-memory $created
            // array), so if the job retries after persisting, the import/notify
            // step resumes instead of being skipped. Bounded to the last week to
            // avoid touching the page's historical backlog.
            $pending = GvaNoticia::where('tipo', 'PDF')
                ->whereNull('import_estado')
                ->where('created_at', '>=', now()->subDays(7))
                ->get()
                ->all();

            $this->autoImportContinua(array_filter($pending, fn (GvaNoticia $n) => $this->isContinuaPdf($n->url)));
            $this->autoImport(array_filter($pending, fn (GvaNoticia $n) => ! $this->isContinuaPdf($n->url)));
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('MonitorGvaJob failed', ['error' => $e->getMessage()]);
    }

    /**
     * Auto-import newly detected weekly ("contínua") adjudication listings and
     * notify affected users. These don't map to a proceso, so they bypass
     * GvaAutoImportService and go straight to the tanda importer.
     *
     * @param  array<int, GvaNoticia>  $created
     */
    private function autoImportContinua(array $created): void
    {
        foreach ($created as $noticia) {
            if (! $this->isContinuaPdf($noticia->url)) {
                continue;
            }

            // Only auto-import (and notify) recent tandas; older ones are kept
            // as notices for the admin to import manually, never auto-notified.
            if (! $this->isRecentContinua($noticia->url)) {
                $noticia->forceFill(['import_estado' => 'sin_proceso', 'import_resumen' => 'Tanda antigua: importación manual.'])->save();

                continue;
            }

            try {
                $exit = \Illuminate\Support\Facades\Artisan::call('adjudicaciones:import-continua', [
                    'path' => $noticia->url,
                    '--notify' => true,
                ]);
                $noticia->forceFill([
                    'importado_en' => now(),
                    'import_estado' => $exit === 0 ? 'ok' : 'error',
                    'import_resumen' => 'Adjudicació contínua '.basename($noticia->url),
                ])->save();
            } catch (\Throwable $e) {
                Log::error('MonitorGvaJob: continua import failed', ['url' => $noticia->url, 'error' => $e->getMessage()]);
                $noticia->forceFill(['import_estado' => 'error', 'import_resumen' => 'Error: '.$e->getMessage()])->save();
            }
        }
    }

    /**
     * A weekly continuous-adjudication listing PDF (YYMMDD_lis_sec/mae.pdf).
     */
    public function isContinuaPdf(string $url): bool
    {
        return (bool) preg_match('/\d{6}_lis_(sec|mae)\.pdf/i', $url);
    }

    /**
     * Whether a continua listing URL is recent (its YYMMDD date within ~16 days),
     * to avoid auto-importing/notifying the page's historical backlog.
     */
    public function isRecentContinua(string $url): bool
    {
        if (! preg_match('/(\d{2})(\d{2})(\d{2})_lis_(?:sec|mae)\.pdf/i', $url, $m)) {
            return false;
        }
        try {
            $fecha = Carbon::createFromDate(2000 + (int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        return $fecha->isFuture() === false && $fecha->diffInDays(Carbon::now()) <= 16;
    }

    /**
     * Fetch + parse PDF links from an arbitrary GVA HTML page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchHtmlPdfNoticias(string $url): array
    {
        try {
            $response = Http::timeout(30)->get($url);
        } catch (\Throwable $e) {
            Log::warning('MonitorGvaJob: page fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return $this->parsePdfLinks($response->body(), $url);
    }

    /**
     * Auto-import any newly detected listing PDFs and notify the admins.
     *
     * @param  array<int, GvaNoticia>  $created
     */
    private function autoImport(array $created): void
    {
        $service = app(GvaAutoImportService::class);
        $importables = array_filter($created, fn (GvaNoticia $n) => $service->isImportable($n));

        if (empty($importables)) {
            return;
        }

        $admins = User::query()->where('is_admin', true)->orWhere('id', 1)->get();

        foreach ($importables as $noticia) {
            $service->import($noticia);

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new ListadoImportadoAdmin($noticia->fresh()));
            }

            Log::info('MonitorGvaJob: auto-import', [
                'url' => $noticia->url,
                'estado' => $noticia->import_estado,
            ]);
        }
    }

    /**
     * Fetch + filter the DOGV RSS feed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchRssNoticias(): array
    {
        try {
            $response = Http::timeout(30)->get(self::RSS_URL);
        } catch (\Throwable $e) {
            Log::warning('MonitorGvaJob: RSS fetch failed', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return $this->parseRss($response->body());
    }

    /**
     * Parse an RSS body, keeping only keyword-matching items.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseRss(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if ($feed === false || ! isset($feed->channel->item)) {
            return [];
        }

        $noticias = [];

        foreach ($feed->channel->item as $item) {
            $titulo = trim((string) $item->title);
            $descripcion = trim((string) $item->description);
            $url = trim((string) $item->link);

            if ($url === '') {
                continue;
            }

            $matched = $this->matchedKeywords($titulo.' '.$descripcion);

            if (empty($matched)) {
                continue;
            }

            $fecha = null;
            $pubDate = trim((string) $item->pubDate);
            if ($pubDate !== '') {
                try {
                    $fecha = Carbon::parse($pubDate)->toDateString();
                } catch (\Throwable $e) {
                    $fecha = null;
                }
            }

            $noticias[] = [
                'titulo' => mb_substr($titulo !== '' ? $titulo : $url, 0, 300),
                'url' => mb_substr($url, 0, 500),
                'fecha_publicacion' => $fecha,
                'tipo' => 'RSS',
                'resumen' => $descripcion !== '' ? $descripcion : null,
                'keywords_matched' => $matched,
            ];
        }

        return $noticias;
    }

    /**
     * Scrape the adjudicaciones page for PDF links.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchPdfNoticias(): array
    {
        try {
            $response = Http::timeout(30)->get(self::ADJUDICACIONES_URL);
        } catch (\Throwable $e) {
            Log::warning('MonitorGvaJob: adjudicaciones fetch failed', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return $this->parsePdfLinks($response->body(), self::ADJUDICACIONES_URL);
    }

    /**
     * Extract PDF links from an HTML page, resolving relative URLs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parsePdfLinks(string $html, string $baseUrl): array
    {
        if (! preg_match_all('/<a[^>]+href=["\']([^"\']+\.pdf(?:\?[^"\']*)?)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $noticias = [];
        $seen = [];

        foreach ($matches as $m) {
            $href = $this->absoluteUrl(html_entity_decode($m[1]), $baseUrl);
            $text = trim(html_entity_decode(strip_tags($m[2])));

            if ($href === '' || isset($seen[$href])) {
                continue;
            }
            $seen[$href] = true;

            $titulo = $text !== '' ? $text : basename(parse_url($href, PHP_URL_PATH) ?: $href);

            // Flag participant-list PDFs so an admin can trigger their import.
            $matched = $this->matchedKeywords($titulo);
            if ($this->isParticipantPdf($href)) {
                $matched[] = 'lista_participantes';
            }

            $noticias[] = [
                'titulo' => mb_substr($titulo, 0, 300),
                'url' => mb_substr($href, 0, 500),
                'fecha_publicacion' => null,
                'tipo' => 'PDF',
                'resumen' => null,
                // notificado stays false (default) so admins review/import it.
                'keywords_matched' => array_values(array_unique($matched)),
            ];
        }

        return $noticias;
    }

    /**
     * Return the keywords present (case/accent-insensitive-ish) in a string.
     *
     * @return array<int, string>
     */
    public function matchedKeywords(string $text): array
    {
        $haystack = mb_strtolower($text);

        return array_values(array_filter(
            self::KEYWORDS,
            fn (string $kw) => mb_strpos($haystack, $kw) !== false,
        ));
    }

    /**
     * Insert notices whose URL is not already stored. Returns the created models.
     *
     * @param  array<int, array<string, mixed>>  $noticias
     * @return array<int, GvaNoticia>
     */
    private function persist(array $noticias): array
    {
        $created = [];

        foreach ($noticias as $n) {
            $noticia = GvaNoticia::firstOrCreate(['url' => $n['url']], $n);

            if ($noticia->wasRecentlyCreated) {
                $created[] = $noticia;
            }
        }

        return $created;
    }

    /**
     * Heuristic: does a PDF URL look like a participant list?
     */
    public function isParticipantPdf(string $url): bool
    {
        return (bool) preg_match('/participantes|participants|lis_/i', $url);
    }

    private function absoluteUrl(string $href, string $baseUrl): string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        if (! isset($parts['scheme'], $parts['host'])) {
            return $href;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        return $origin.'/'.ltrim($href, '/');
    }
}
