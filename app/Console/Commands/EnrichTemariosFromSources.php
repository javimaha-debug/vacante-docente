<?php

namespace App\Console\Commands;

use App\Models\DetectedDocument;
use App\Models\SyncState;
use App\Models\TemarioOficial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cross-checks external sources (unions, ANPE, BOE) for temario material that
 * differs from the official BOE data. NEVER overwrites official temas: it only
 * flags candidates as detected_documents for superadmin review.
 */
class EnrichTemariosFromSources extends Command
{
    protected $signature = 'temarios:enrich-sources';

    protected $description = 'Scan union/ANPE sources for temario material and flag differences for review.';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/124.0 Safari/537.36 Doccentia-Monitor/1.0';

    public function handle(): int
    {
        $flagged = 0;

        // Build a normalized index of known specialty names to match links against.
        $especialidades = TemarioOficial::pluck('especialidad_nombre')
            ->map(fn ($n) => $this->normalize($n))->all();

        foreach ($this->sources() as $url) {
            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(30)->get($url);
            } catch (\Throwable $e) {
                Log::warning('temarios:enrich-sources fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

                continue;
            }
            if (! $response->successful()) {
                continue;
            }

            foreach ($this->extractTemarioLinks($response->body(), $url) as $cand) {
                $haystack = $this->normalize($cand['title']);
                if (! str_contains($haystack, 'temari')) {
                    continue;
                }
                // Only flag when it references a specialty we already track.
                $matchesEspecialidad = $especialidades === []
                    || collect($especialidades)->contains(fn ($n) => $n !== '' && str_contains($haystack, $n));
                if (! $matchesEspecialidad) {
                    continue;
                }

                $exists = DetectedDocument::where('source_url', $cand['url'])
                    ->orWhere('pdf_url', $cand['url'])->exists();
                if ($exists) {
                    continue;
                }

                DetectedDocument::create([
                    'title' => 'Temario (fuente externa): '.$cand['title'],
                    'detected_at' => now(),
                    'source_url' => $cand['url'],
                    'document_type' => 'otro',
                    'status' => 'pending',
                    'pdf_url' => $cand['is_pdf'] ? $cand['url'] : null,
                    'superadmin_notes' => 'Posible material de temario detectado para revisión; no sobreescribe el temario oficial del BOE.',
                ]);
                $flagged++;
            }
        }

        SyncState::record('temarios_enrich_sources', ['flagged' => $flagged]);
        $this->info("Fuentes de temario revisadas · {$flagged} documento(s) marcados para revisión.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{title:string, url:string, is_pdf:bool}>
     */
    private function extractTemarioLinks(string $html, string $baseUrl): array
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
            $seen[$href] = true;
            $out[] = [
                'title' => mb_substr($text, 0, 400),
                'url' => mb_substr($href, 0, 690),
                'is_pdf' => (bool) preg_match('/\.pdf(\?|$)/i', $href),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function sources(): array
    {
        $configured = config('services.temarios.sources');
        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        return [
            'https://anpecomunidadvalenciana.es',
            'https://ccoo.es/ensenyament',
        ];
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

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ò' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n']);

        return preg_replace('/\s+/', ' ', $value);
    }
}
