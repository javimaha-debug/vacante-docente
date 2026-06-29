<?php

namespace App\Http\Controllers\Api\Integrations;

use Illuminate\Support\Facades\Http;

class GoogleDriveController extends AbstractCloudImportController
{
    private const SCOPE = 'https://www.googleapis.com/auth/drive.readonly';

    protected function provider(): string
    {
        return 'google_drive';
    }

    protected function isConfigured(): bool
    {
        return (bool) config('services.google.client_id') && (bool) config('services.google.client_secret');
    }

    protected function redirectUri(): string
    {
        return (string) env('GOOGLE_DRIVE_REDIRECT_URI', rtrim(config('app.url'), '/').'/api/v1/integrations/google-drive/callback');
    }

    protected function authorizeUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    protected function exchangeCode(string $code): array
    {
        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        return [
            'access_token' => $res['access_token'],
            'refresh_token' => $res['refresh_token'] ?? null,
            'expires_in' => $res['expires_in'] ?? null,
        ];
    }

    protected function refreshToken(string $refreshToken): array
    {
        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'refresh_token' => $refreshToken,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'grant_type' => 'refresh_token',
        ])->throw()->json();

        return ['access_token' => $res['access_token'], 'expires_in' => $res['expires_in'] ?? null];
    }

    protected function listFiles(string $accessToken): array
    {
        $mimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $q = '('.implode(' or ', array_map(fn ($m) => "mimeType='{$m}'", $mimes)).") and trashed=false";

        $res = Http::withToken($accessToken)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => $q,
            'fields' => 'files(id,name,mimeType,size)',
            'pageSize' => 100,
        ])->throw()->json();

        return collect($res['files'] ?? [])->map(fn ($f) => [
            'id' => $f['id'],
            'name' => $f['name'],
            'mime' => $f['mimeType'] ?? null,
            'size' => isset($f['size']) ? (int) $f['size'] : null,
        ])->all();
    }

    protected function downloadFile(string $accessToken, string $fileId): array
    {
        $meta = Http::withToken($accessToken)
            ->get("https://www.googleapis.com/drive/v3/files/{$fileId}", ['fields' => 'name,mimeType'])
            ->throw()->json();

        $contents = Http::withToken($accessToken)
            ->get("https://www.googleapis.com/drive/v3/files/{$fileId}", ['alt' => 'media'])
            ->throw()->body();

        return ['contents' => $contents, 'name' => $meta['name'] ?? 'documento', 'mime' => $meta['mimeType'] ?? null];
    }

    protected function sourceLabel(): string
    {
        return 'Google Drive';
    }
}
