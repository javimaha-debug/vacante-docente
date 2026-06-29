<?php

namespace App\Services;

use App\Models\AcademicCalendarEvent;
use App\Models\DetectedDocument;
use App\Models\MonitoredSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Scans monitored sources for new official documents (listings, resolutions,
 * announcements). Detected documents land as "pending" for a superadmin to
 * validate and publish; relevant ones also suggest a calendar event.
 */
class DocumentMonitorService
{
    /** Keywords (accent-insensitive) that mark a link/title as relevant. */
    public const KEYWORDS = [
        'listado', 'llistat', 'provisional', 'definitivo', 'definitiu', 'vacantes',
        'vacants', 'resolucion', 'resolucio', 'interins', 'interinos', 'suprimits',
        'adjudicacio', 'adjudicacion', 'borsa', 'bolsa', 'convocatoria', 'convocatoria',
    ];

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/124.0 Safari/537.36 Doccentia-Monitor/1.0';

    /**
     * Scan one source: fetch the page, detect new documents, persist them and
     * suggest calendar events. Returns a per-source summary.
     *
     * @return array{source:string, nuevos:int, eventos:int, titulos:array<int,string>, error:?string}
     */
    public function scan(MonitoredSource $source): array
    {
        $out = ['source' => $source->name, 'nuevos' => 0, 'eventos' => 0, 'titulos' => [], 'error' => null];

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(30)->get($source->url);
        } catch (\Throwable $e) {
            Log::warning('DocumentMonitor: fetch failed', ['url' => $source->url, 'error' => $e->getMessage()]);
            $out['error'] = $e->getMessage();
            $source->forceFill(['last_checked_at' => now()])->save();

            return $out;
        }

        $source->forceFill(['last_checked_at' => now()])->save();

        if (! $response->successful()) {
            $out['error'] = 'HTTP '.$response->status();

            return $out;
        }

        foreach ($this->extractCandidates($response->body(), $source->url) as $cand) {
            // Dedup: same source_url already detected for this source.
            $exists = DetectedDocument::where('source_id', $source->id)
                ->where(function ($q) use ($cand) {
                    $q->where('source_url', $cand['source_url']);
                    if ($cand['pdf_url']) {
                        $q->orWhere('pdf_url', $cand['pdf_url']);
                    }
                })
                ->exists();

            if ($exists) {
                continue;
            }

            $doc = DetectedDocument::create([
                'source_id' => $source->id,
                'title' => $cand['title'],
                'detected_at' => now(),
                'source_url' => $cand['source_url'],
                'document_type' => $cand['document_type'],
                'status' => 'pending',
                'pdf_url' => $cand['pdf_url'],
            ]);

            if ($cand['pdf_url']) {
                $this->tryDownload($doc, $cand['pdf_url']);
            }

            $out['nuevos']++;
            $out['titulos'][] = $cand['title'];

            if ($this->suggestCalendarEvent($doc)) {
                $out['eventos']++;
            }
        }

        return $out;
    }

    /**
     * Pull candidate documents from a page: PDF links and keyword-matching
     * article links.
     *
     * @return array<int, array{title:string, source_url:string, pdf_url:?string, document_type:string}>
     */
    public function extractCandidates(string $html, string $baseUrl): array
    {
        if (! preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $candidates = [];
        $seen = [];

        foreach ($matches as $m) {
            $href = $this->absoluteUrl(html_entity_decode(trim($m[1])), $baseUrl);
            $text = trim(html_entity_decode(strip_tags($m[2])));
            if ($href === '' || isset($seen[$href])) {
                continue;
            }

            $isPdf = (bool) preg_match('/\.pdf(\?|$)/i', $href);
            $haystack = $this->normalize($text.' '.$href);
            $matchesKeyword = $this->matchesKeyword($haystack);

            // Keep PDFs that look relevant, and relevant article links.
            if (! $isPdf && ! $matchesKeyword) {
                continue;
            }
            if ($isPdf && ! $matchesKeyword && ! $this->matchesKeyword($this->normalize($href))) {
                // A PDF with no relevant keyword anywhere — skip generic PDFs.
                continue;
            }

            $seen[$href] = true;
            $title = $text !== '' ? $text : basename(parse_url($href, PHP_URL_PATH) ?: $href);

            $candidates[] = [
                'title' => mb_substr($title, 0, 480),
                'source_url' => mb_substr($href, 0, 690),
                'pdf_url' => $isPdf ? mb_substr($href, 0, 690) : null,
                'document_type' => $this->classify($haystack),
            ];
        }

        return $candidates;
    }

    /** Classify a document by keywords present in its title/url. */
    public function classify(string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'provisional') => 'listado_provisional',
            str_contains($normalized, 'definitiu') || str_contains($normalized, 'definitivo') => 'listado_definitivo',
            str_contains($normalized, 'vacant') => 'vacantes',
            str_contains($normalized, 'resolucio') || str_contains($normalized, 'resolucion') => 'resolucion',
            str_contains($normalized, 'convocatoria') => 'convocatoria',
            default => 'otro',
        };
    }

    /**
     * Suggest an estimated, superadmin-only calendar event for documents that
     * map to a milestone. Returns true when an event was created.
     */
    public function suggestCalendarEvent(DetectedDocument $doc): bool
    {
        $type = match ($doc->document_type) {
            'listado_provisional' => 'listado_provisional',
            'listado_definitivo' => 'listado_definitivo',
            default => str_contains($this->normalize($doc->title), 'adjudicacio') ? 'adjudicacion' : null,
        };

        if ($type === null) {
            return false;
        }

        // One suggestion per detected document.
        if (AcademicCalendarEvent::where('source_document_id', $doc->id)->exists()) {
            return false;
        }

        AcademicCalendarEvent::create([
            'title' => 'Sugerido: '.mb_substr($doc->title, 0, 280),
            'event_type' => $type,
            'event_date' => now()->toDateString(), // placeholder — superadmin sets the real date
            'source_document_id' => $doc->id,
            'is_confirmed' => false,
            'is_estimated' => true,
            'affects' => 'interinos',
            'visibility' => 'superadmin_only',
        ]);

        return true;
    }

    /** Download the PDF to local storage; best-effort (failures are non-fatal). */
    private function tryDownload(DetectedDocument $doc, string $url): void
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(60)->get($url);
            if (! $response->successful()) {
                return;
            }
            $name = basename(parse_url($url, PHP_URL_PATH) ?: 'documento.pdf');
            if (! str_ends_with(mb_strtolower($name), '.pdf')) {
                $name .= '.pdf';
            }
            $relative = 'documents/'.$doc->id.'_'.$name;
            Storage::disk('local')->put($relative, $response->body());
            $doc->forceFill(['pdf_path' => $relative])->save();
        } catch (\Throwable $e) {
            Log::info('DocumentMonitor: pdf download skipped', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }

    private function matchesKeyword(string $normalized): bool
    {
        foreach (self::KEYWORDS as $kw) {
            if (str_contains($normalized, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'ä' => 'a', 'è' => 'e', 'é' => 'e', 'ë' => 'e',
            'í' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ö' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', ' ', $value);
    }

    private function absoluteUrl(string $href, string $baseUrl): string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }
        if (str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'javascript:')) {
            return '';
        }
        $parts = parse_url($baseUrl);
        if (! isset($parts['scheme'], $parts['host'])) {
            return $href;
        }
        $origin = $parts['scheme'].'://'.$parts['host'];

        return str_starts_with($href, '/') ? $origin.$href : $origin.'/'.ltrim($href, '/');
    }
}
