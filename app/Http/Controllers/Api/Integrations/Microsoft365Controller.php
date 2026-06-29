<?php

namespace App\Http\Controllers\Api\Integrations;

use Illuminate\Support\Facades\Http;

class Microsoft365Controller extends AbstractCloudImportController
{
    private const SCOPE = 'Files.Read offline_access';

    protected function provider(): string
    {
        return 'microsoft_365';
    }

    protected function isConfigured(): bool
    {
        return (bool) config('services.microsoft.client_id') && (bool) config('services.microsoft.client_secret');
    }

    private function tenant(): string
    {
        return (string) (config('services.microsoft.tenant') ?: 'common');
    }

    protected function redirectUri(): string
    {
        return (string) env('MICROSOFT_DRIVE_REDIRECT_URI', rtrim(config('app.url'), '/').'/api/v1/integrations/microsoft/callback');
    }

    protected function authorizeUrl(string $state): string
    {
        return "https://login.microsoftonline.com/{$this->tenant()}/oauth2/v2.0/authorize?".http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'response_mode' => 'query',
            'scope' => self::SCOPE,
            'state' => $state,
        ]);
    }

    protected function exchangeCode(string $code): array
    {
        $res = Http::asForm()->post("https://login.microsoftonline.com/{$this->tenant()}/oauth2/v2.0/token", [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
            'scope' => self::SCOPE,
        ])->throw()->json();

        return [
            'access_token' => $res['access_token'],
            'refresh_token' => $res['refresh_token'] ?? null,
            'expires_in' => $res['expires_in'] ?? null,
        ];
    }

    protected function refreshToken(string $refreshToken): array
    {
        $res = Http::asForm()->post("https://login.microsoftonline.com/{$this->tenant()}/oauth2/v2.0/token", [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'scope' => self::SCOPE,
        ])->throw()->json();

        return ['access_token' => $res['access_token'], 'expires_in' => $res['expires_in'] ?? null];
    }

    protected function listFiles(string $accessToken): array
    {
        $res = Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me/drive/root/children', ['$top' => 100])
            ->throw()->json();

        return collect($res['value'] ?? [])
            ->filter(fn ($f) => isset($f['file']) && $this->isImportable($f['name'] ?? ''))
            ->map(fn ($f) => [
                'id' => $f['id'],
                'name' => $f['name'],
                'mime' => $f['file']['mimeType'] ?? null,
                'size' => isset($f['size']) ? (int) $f['size'] : null,
            ])->values()->all();
    }

    protected function downloadFile(string $accessToken, string $fileId): array
    {
        $meta = Http::withToken($accessToken)
            ->get("https://graph.microsoft.com/v1.0/me/drive/items/{$fileId}")
            ->throw()->json();

        $contents = Http::withToken($accessToken)
            ->get("https://graph.microsoft.com/v1.0/me/drive/items/{$fileId}/content")
            ->throw()->body();

        return [
            'contents' => $contents,
            'name' => $meta['name'] ?? 'documento',
            'mime' => $meta['file']['mimeType'] ?? null,
        ];
    }

    private function isImportable(string $name): bool
    {
        return (bool) preg_match('/\.(pdf|docx?)$/i', $name);
    }

    protected function sourceLabel(): string
    {
        return 'Microsoft 365';
    }
}
