<?php

namespace App\Services;

use App\Models\Convocatoria;
use App\Models\DetectedDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Detects new oposición calls from the BOE API and scraped union/official
 * pages. New detections land as convocatorias flagged pendiente_revision for a
 * superadmin to validate (set estado/fechas/urls) and publish.
 */
class ConvocatoriaMonitorService
{
    /** Keywords (accent-insensitive) that mark a link/title as a convocatoria. */
    public const KEYWORDS = [
        'convocatoria', 'convocatoria', 'oposicion', 'oposicio', 'procediment selectiu',
        'procedimiento selectivo', 'ingreso cuerpo', 'ingreso al cuerpo', 'ingres cos',
        'cossos docents', 'cuerpos docentes',
    ];

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/124.0 Safari/537.36 Doccentia-Monitor/1.0';

    /** Fetch a page and return relevant convocatoria candidate links. */
    public function scrape(string $url): array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(30)->get($url);
        } catch (\Throwable $e) {
            Log::warning('ConvocatoriaMonitor: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return $this->extractCandidates($response->body(), $url);
    }

    /**
     * @return array<int, array{titulo:string, url:string, pdf_url:?string}>
     */
    public function extractCandidates(string $html, string $baseUrl): array
    {
        if (! preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($matches as $m) {
            $href = $this->absoluteUrl(html_entity_decode(trim($m[1])), $baseUrl);
            $text = trim(html_entity_decode(strip_tags($m[2])));
            if ($href === '' || $text === '' || isset($seen[$href])) {
                continue;
            }
            if (! $this->looksLikeConvocatoria($text.' '.$href)) {
                continue;
            }
            $seen[$href] = true;
            $isPdf = (bool) preg_match('/\.pdf(\?|$)/i', $href);
            $out[] = [
                'titulo' => mb_substr($text, 0, 290),
                'url' => mb_substr($href, 0, 490),
                'pdf_url' => $isPdf ? mb_substr($href, 0, 490) : null,
            ];
        }

        return $out;
    }

    public function looksLikeConvocatoria(string $text): bool
    {
        $t = $this->normalize($text);
        foreach (self::KEYWORDS as $kw) {
            if (str_contains($t, $kw)) {
                return true;
            }
        }

        return false;
    }

    /** Detect the cuerpo from a title; null when ambiguous. */
    public function detectCuerpo(string $text): ?string
    {
        $t = $this->normalize($text);

        return match (true) {
            str_contains($t, 'maestro') || str_contains($t, 'mestre') || str_contains($t, 'primaria') || str_contains($t, 'infantil') => 'maestros',
            str_contains($t, 'formacio professional') || str_contains($t, 'formacion profesional') || str_contains($t, ' fp ') => 'fp',
            str_contains($t, 'secundaria') || str_contains($t, 'profesor') || str_contains($t, 'professor') => 'secundaria',
            default => null,
        };
    }

    /**
     * Register a detected convocatoria if no similar title already exists.
     *
     * @return array{status:string, convocatoria:?Convocatoria}
     */
    public function register(string $titulo, string $comunidad, string $estado, ?string $pdfUrl = null): array
    {
        if ($this->existsSimilar($titulo)) {
            return ['status' => 'exists', 'convocatoria' => null];
        }

        $sourceDocId = null;
        if ($pdfUrl) {
            $sourceDocId = DetectedDocument::where('pdf_url', $pdfUrl)
                ->orWhere('source_url', $pdfUrl)->value('id');
        }

        $convocatoria = Convocatoria::create([
            'titulo' => $titulo,
            'comunidad_autonoma' => $comunidad,
            'cuerpo' => $this->detectCuerpo($titulo),
            'estado' => $estado,
            'pendiente_revision' => true,
            'url_oficial' => $pdfUrl,
            'source_document_id' => $sourceDocId,
        ]);

        return ['status' => 'created', 'convocatoria' => $convocatoria];
    }

    /** True when a convocatoria with a very similar title already exists. */
    public function existsSimilar(string $titulo): bool
    {
        $needle = $this->normalize($titulo);

        foreach (Convocatoria::query()->pluck('titulo') as $existing) {
            $hay = $this->normalize($existing);
            if ($hay === $needle || str_contains($hay, $needle) || str_contains($needle, $hay)) {
                return true;
            }
            similar_text($hay, $needle, $percent);
            if ($percent >= 85.0) {
                return true;
            }
        }

        return false;
    }

    public function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'ä' => 'a', 'è' => 'e', 'é' => 'e', 'ë' => 'e',
            'í' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ö' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', '·' => '',
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
