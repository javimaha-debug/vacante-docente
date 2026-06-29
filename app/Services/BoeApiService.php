<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client over the BOE (Boletín Oficial del Estado) open-data API.
 * https://www.boe.es/datosabiertos/api/
 *
 * The search payload shape varies, so parsing is intentionally defensive: we
 * accept several common key names and never throw on a malformed response.
 */
class BoeApiService
{
    private const USER_AGENT = 'Doccentia-Normativa/1.0 (+https://doccentia.es)';

    /** Resolve the configurable search endpoint. */
    private function searchUrl(): string
    {
        return (string) config('services.boe.search_url', 'https://www.boe.es/datosabiertos/api/boe/api/search');
    }

    /**
     * Run a free-text search against the BOE API and return normalized hits.
     *
     * @return array<int, array{titulo:string, url_oficial:string, fecha_publicacion:?string, identificador:?string}>
     */
    public function search(string $query, int $limit = 10): array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => self::USER_AGENT,
            ])->timeout(30)->get($this->searchUrl(), [
                'query' => $query,
                'q' => $query,
                'limit' => $limit,
            ]);
        } catch (\Throwable $e) {
            Log::warning('BoeApiService: request failed', ['query' => $query, 'error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            Log::info('BoeApiService: non-200', ['query' => $query, 'status' => $response->status()]);

            return [];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return [];
        }

        $items = $this->extractItems($json);
        $hits = [];

        foreach (array_slice($items, 0, $limit) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $titulo = $this->firstString($item, ['titulo', 'title', 'nombre', 'name']);
            $url = $this->firstString($item, ['url_oficial', 'url', 'permalink', 'urlPdf', 'url_html', 'enlace']);
            if ($titulo === null || $url === null) {
                continue;
            }
            $hits[] = [
                'titulo' => mb_substr(trim($titulo), 0, 290),
                'url_oficial' => mb_substr(trim($url), 0, 490),
                'fecha_publicacion' => $this->parseDate($this->firstString($item, ['fecha_publicacion', 'fecha', 'date', 'fecha_disposicion'])),
                'identificador' => $this->firstString($item, ['identificador', 'id', 'codigo']),
            ];
        }

        return $hits;
    }

    /**
     * Find the list of result rows inside a variety of envelope shapes.
     *
     * @param  array<mixed>  $json
     * @return array<int, mixed>
     */
    private function extractItems(array $json): array
    {
        foreach (['results', 'data', 'items', 'documentos', 'resultados'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                // Some APIs nest one more level (data.results).
                if (isset($json[$key]['results']) && is_array($json[$key]['results'])) {
                    return $json[$key]['results'];
                }

                return array_is_list($json[$key]) ? $json[$key] : [$json[$key]];
            }
        }

        return array_is_list($json) ? $json : [];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $keys
     */
    private function firstString(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && is_string($item[$key]) && trim($item[$key]) !== '') {
                return $item[$key];
            }
        }

        return null;
    }

    private function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            // BOE sometimes uses YYYYMMDD.
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)) {
                return "{$m[1]}-{$m[2]}-{$m[3]}";
            }

            return null;
        }
    }
}
