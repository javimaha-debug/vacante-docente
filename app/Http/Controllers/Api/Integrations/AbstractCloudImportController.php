<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDocumentJob;
use App\Models\UserDocument;
use App\Models\UserIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared OAuth + import flow for per-user cloud storage integrations
 * (Google Drive, Microsoft 365). Tokens are stored encrypted per user.
 *
 * The connect endpoint is called by the SPA (bearer auth) and returns the
 * provider consent URL; the browser then navigates there. The callback is hit
 * by the provider (no bearer), so the user is recovered from a signed `state`.
 */
abstract class AbstractCloudImportController extends Controller
{
    /** Provider key stored on user_integrations (google_drive | microsoft_365). */
    abstract protected function provider(): string;

    abstract protected function isConfigured(): bool;

    abstract protected function authorizeUrl(string $state): string;

    /** @return array{access_token:string, refresh_token:?string, expires_in:?int} */
    abstract protected function exchangeCode(string $code): array;

    /** @return array{access_token:string, expires_in:?int} */
    abstract protected function refreshToken(string $refreshToken): array;

    /** @return array<int, array{id:string, name:string, mime:?string, size:?int}> */
    abstract protected function listFiles(string $accessToken): array;

    /** @return array{contents:string, name:string, mime:?string} */
    abstract protected function downloadFile(string $accessToken, string $fileId): array;

    abstract protected function sourceLabel(): string;

    /** Step 1: hand the SPA the consent URL to navigate to. */
    public function connect(Request $request): JsonResponse
    {
        if (! $this->isConfigured()) {
            return response()->json(['message' => 'Esta integración no está configurada todavía.'], 503);
        }

        $state = Crypt::encryptString(json_encode([
            'uid' => $request->user()->id,
            'nonce' => Str::random(16),
            'exp' => now()->addMinutes(10)->timestamp,
        ]));

        return response()->json(['url' => $this->authorizeUrl($state)]);
    }

    /** Step 2: provider callback → store tokens, bounce back to the SPA. */
    public function callback(Request $request): RedirectResponse
    {
        $userId = $this->userFromState($request->query('state'));
        $code = $request->query('code');

        if (! $userId || ! $code || ! $this->isConfigured()) {
            return redirect('/dashboard/mis-documentos?integration_error=1');
        }

        try {
            $tokens = $this->exchangeCode($code);
        } catch (\Throwable $e) {
            return redirect('/dashboard/mis-documentos?integration_error=1');
        }

        UserIntegration::updateOrCreate(
            ['user_id' => $userId, 'provider' => $this->provider()],
            [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_at' => ($tokens['expires_in'] ?? null) ? now()->addSeconds((int) $tokens['expires_in']) : null,
            ],
        );

        return redirect('/dashboard/mis-documentos?connected='.$this->provider());
    }

    /** List importable files (PDF/Word) from the connected account. */
    public function files(Request $request): JsonResponse
    {
        $integration = $this->integrationOrFail($request);
        if (! $integration) {
            return response()->json(['connected' => false, 'data' => []]);
        }

        try {
            $token = $this->freshAccessToken($integration);
            $files = $this->listFiles($token);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudieron listar los archivos.'], 502);
        }

        return response()->json(['connected' => true, 'data' => $files]);
    }

    /** Import a selected remote file into the user's storage. */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file_id' => ['required', 'string'],
            'folder_id' => ['nullable', 'integer', 'exists:user_folders,id'],
        ]);

        $integration = $this->integrationOrFail($request);
        if (! $integration) {
            return response()->json(['message' => 'Integración no conectada.'], 409);
        }

        $user = $request->user();

        try {
            $token = $this->freshAccessToken($integration);
            $file = $this->downloadFile($token, $data['file_id']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudo descargar el archivo.'], 502);
        }

        $bytes = strlen($file['contents']);
        if ($user->exceedsStorage($bytes)) {
            return response()->json(['message' => 'No tienes espacio suficiente para importar este archivo.'], 422);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin');
        $path = "users/{$user->id}/docs/".Str::uuid()->toString().'.'.$ext;
        Storage::disk(config('documents.disk'))->put($path, $file['contents']);

        $doc = UserDocument::create([
            'user_id' => $user->id,
            'folder_id' => $data['folder_id'] ?? null,
            'name' => $file['name'],
            'disk_path' => $path,
            'mime_type' => $file['mime'],
            'size_bytes' => $bytes,
            'type' => $this->typeFromExt($ext),
            'source' => $this->provider(),
            'external_id' => $data['file_id'],
            'processing_status' => 'pending',
        ]);

        $user->increment('storage_used_bytes', $bytes);
        ProcessDocumentJob::dispatch($doc->id);

        return response()->json(['data' => $doc], 201);
    }

    // --- helpers ---

    protected function integrationOrFail(Request $request): ?UserIntegration
    {
        return UserIntegration::where('user_id', $request->user()->id)
            ->where('provider', $this->provider())
            ->first();
    }

    /** Return a valid access token, refreshing it when expired. */
    protected function freshAccessToken(UserIntegration $integration): string
    {
        if ($integration->isExpired() && $integration->refresh_token) {
            $refreshed = $this->refreshToken($integration->refresh_token);
            $integration->forceFill([
                'access_token' => $refreshed['access_token'],
                'expires_at' => ($refreshed['expires_in'] ?? null) ? now()->addSeconds((int) $refreshed['expires_in']) : null,
            ])->save();
        }

        return (string) $integration->access_token;
    }

    protected function userFromState(?string $state): ?int
    {
        if (! $state) {
            return null;
        }
        try {
            $payload = json_decode(Crypt::decryptString($state), true);
        } catch (\Throwable $e) {
            return null;
        }
        if (! is_array($payload) || ($payload['exp'] ?? 0) < Carbon::now()->timestamp) {
            return null;
        }

        return (int) ($payload['uid'] ?? 0) ?: null;
    }

    protected function typeFromExt(string $ext): string
    {
        return match ($ext) {
            'pdf' => 'pdf',
            'doc', 'docx' => 'word',
            'jpg', 'jpeg', 'png', 'webp' => 'image',
            default => 'other',
        };
    }
}
