<?php

namespace App\Services;

use App\Models\NormativaDocumento;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared helpers for the BOE/DOGV normativa sync commands: category + language
 * detection, idempotent upsert by url_oficial, and best-effort PDF download.
 */
class NormativaSyncService
{
    private const USER_AGENT = 'Doccentia-Normativa/1.0 (+https://doccentia.es)';

    /**
     * Create a normativa document if its url_oficial is new. Returns:
     *  - 'created' + the model when inserted
     *  - 'exists' when a document with that url already exists
     *
     * @param  array{titulo:string, url_oficial:string, categoria?:?string, comunidad_autonoma?:string, fecha_publicacion?:?string, fuente:string, idioma?:?string}  $data
     * @return array{status:string, doc:?NormativaDocumento}
     */
    public function upsertFromHit(array $data): array
    {
        $url = $data['url_oficial'] ?? null;
        if (! $url) {
            return ['status' => 'skipped', 'doc' => null];
        }

        if (NormativaDocumento::where('url_oficial', $url)->exists()) {
            return ['status' => 'exists', 'doc' => null];
        }

        $doc = NormativaDocumento::create([
            'titulo' => $data['titulo'],
            'categoria' => $data['categoria'] ?? $this->categoriaFromTitulo($data['titulo']),
            'comunidad_autonoma' => $data['comunidad_autonoma'] ?? 'nacional',
            'url_oficial' => $url,
            'fecha_publicacion' => $data['fecha_publicacion'] ?? null,
            'vigente' => true,
            'fuente' => $data['fuente'],
            'idioma' => $data['idioma'] ?? null,
            'published_by' => $this->firstSuperadminId(),
            'published_at' => now(),
        ]);

        if (Str::endsWith(Str::lower($url), '.pdf')) {
            $this->downloadPdf($doc, $url);
        }

        return ['status' => 'created', 'doc' => $doc];
    }

    /** Detect the document category from its title. */
    public function categoriaFromTitulo(string $titulo): string
    {
        $t = $this->normalize($titulo);

        return match (true) {
            str_contains($t, 'ley organica') || str_contains($t, 'llei organica') || str_contains($t, 'lomloe') || str_contains($t, 'loe') => 'ley_organica',
            str_contains($t, 'real decreto') || str_contains($t, 'decreto') || str_contains($t, 'decret') => 'decreto',
            str_contains($t, 'orden') || str_contains($t, 'ordre') => 'orden',
            str_contains($t, 'resolucion') || str_contains($t, 'resolucio') => 'resolucion',
            str_contains($t, 'instruccion') || str_contains($t, 'instruccio') => 'instrucciones',
            default => 'otro',
        };
    }

    /**
     * Detect document language (valenciano/castellano) from a text sample.
     * Returns null when there's not enough signal.
     */
    public function detectIdioma(string $text): ?string
    {
        $t = ' '.$this->normalize($text).' ';

        $valMarkers = [' i ', ' amb ', ' per a ', ' aquest ', ' ensenyament', ' valencia', ' decret', ' llei', ' curriculum', ' batxillerat', ' ordre', ' resolucio'];
        $castMarkers = [' y ', ' con ', ' para ', ' este ', ' ensenanza', ' decreto', ' ley ', ' curriculo', ' bachillerato', ' orden ', ' resolucion'];

        $val = 0;
        $cast = 0;
        foreach ($valMarkers as $m) {
            $val += substr_count($t, $m);
        }
        foreach ($castMarkers as $m) {
            $cast += substr_count($t, $m);
        }

        if ($val === 0 && $cast === 0) {
            return null;
        }

        return $val > $cast ? 'valenciano' : 'castellano';
    }

    /** Best-effort PDF download to the public disk under normativa/{slug}.pdf. */
    public function downloadPdf(NormativaDocumento $doc, string $url): void
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(60)->get($url);
            if (! $response->successful()) {
                return;
            }
            $slug = Str::slug(Str::limit($doc->titulo, 80, '')) ?: 'documento-'.$doc->id;
            $relative = 'normativa/'.$slug.'-'.$doc->id.'.pdf';
            Storage::disk('public')->put($relative, $response->body());
            $doc->forceFill(['pdf_path' => $relative])->save();
        } catch (\Throwable $e) {
            Log::info('NormativaSync: pdf download skipped', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }

    /** The id of the first superadmin (document owner), or null. */
    public function firstSuperadminId(): ?int
    {
        return User::where('role', 'superadmin')->orderBy('id')->value('id')
            ?? User::where('is_admin', true)->orderBy('id')->value('id');
    }

    /** Education keywords (accent-insensitive) that mark a DOGV link as relevant. */
    public const DOGV_KEYWORDS = [
        'curriculum', 'curriculo', 'decret', 'decreto', 'eso', 'batxillerat', 'bachillerato',
        'formacio professional', 'formacion profesional', 'instruccions', 'instrucciones',
        'inici de curs', 'inicio de curso', 'interins', 'interinos', 'personal docent', 'resolucio',
    ];

    /**
     * Extract relevant PDF links from a DOGV listing page.
     *
     * @return array<int, array{titulo:string, url_oficial:string}>
     */
    public function extractEducationPdfs(string $html, string $baseUrl): array
    {
        if (! preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $hits = [];
        $seen = [];
        foreach ($matches as $m) {
            $href = $this->absoluteUrl(html_entity_decode(trim($m[1])), $baseUrl);
            $text = trim(html_entity_decode(strip_tags($m[2])));
            if ($href === '' || isset($seen[$href])) {
                continue;
            }
            $isPdf = (bool) preg_match('/\.pdf(\?|$)/i', $href);
            if (! $isPdf) {
                continue;
            }
            $haystack = $this->normalize($text.' '.$href);
            $relevant = false;
            foreach (self::DOGV_KEYWORDS as $kw) {
                if (str_contains($haystack, $kw)) {
                    $relevant = true;
                    break;
                }
            }
            if (! $relevant) {
                continue;
            }
            $seen[$href] = true;
            $titulo = $text !== '' ? $text : basename(parse_url($href, PHP_URL_PATH) ?: $href);
            $hits[] = [
                'titulo' => mb_substr($titulo, 0, 290),
                'url_oficial' => mb_substr($href, 0, 490),
            ];
        }

        return $hits;
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

    /** Lowercase + strip accents for keyword matching. */
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

    public function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
