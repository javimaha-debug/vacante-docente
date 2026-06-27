<?php

namespace App\Jobs;

use App\Models\GvaNoticia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonitorGvaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const RSS_URL = 'https://dogv.gva.es/portal/rss/rss.xhtml';

    private const ADJUDICACIONES_URL = 'https://ceice.gva.es/va/web/rrhh-educacion/adjudicaciones';

    /** Keywords (lower-case) that mark a notice as relevant to docentes. */
    public const KEYWORDS = [
        'adjudicació', 'adjudicacion', 'personal docent', 'interí', 'interino',
        'vacants', 'vacantes', 'borsa', 'bolsa',
    ];

    public function handle(): void
    {
        $rss = $this->fetchRssNoticias();
        $pdfs = $this->fetchPdfNoticias();

        $created = $this->persist($rss) + $this->persist($pdfs);

        Log::info("MonitorGvaJob: {$created} new GVA notice(s) stored.");
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
     * Insert notices whose URL is not already stored. Returns the count created.
     *
     * @param  array<int, array<string, mixed>>  $noticias
     */
    private function persist(array $noticias): int
    {
        $created = 0;

        foreach ($noticias as $n) {
            $noticia = GvaNoticia::firstOrCreate(['url' => $n['url']], $n);

            if ($noticia->wasRecentlyCreated) {
                $created++;
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
