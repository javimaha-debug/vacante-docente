<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\UserIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Data-subject rights for the authenticated account (RGPD/LOPDGDD):
 *  - export(): right to data portability (art. 20) — a machine-readable copy
 *    of all the personal data we hold about the user.
 *  - destroy(): right to erasure / "derecho de supresión" (art. 17) — wipes the
 *    account and every piece of data attached to it, including stored files.
 */
class AccountController extends Controller
{
    /**
     * Download every piece of personal data tied to the account as a single
     * JSON document (right to portability, RGPD art. 20).
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'ccaa', 'colectivo',
            'especialidades.specialty',
            'historial.specialty', 'historial.centroAdjudicado', 'historial.proceso',
        ]);

        $payload = [
            'exportado_en' => now()->toIso8601String(),
            'aviso' => 'Copia de tus datos personales en Doccentia (RGPD art. 20). '
                .'Para descargar los ficheros que has subido, usa la sección «Mis documentos».',
            'cuenta' => [
                'id' => $user->id,
                'nombre' => $user->name,
                'email' => $user->email,
                'nombre_gva' => $user->nombre_gva,
                'avatar_url' => $user->avatar_url,
                'idioma' => $user->locale,
                'modo_activo' => $user->modo_activo,
                'ccaa' => $user->ccaa?->name,
                'colectivo' => $user->colectivo?->name,
                'direccion_origen' => $user->direccion_origen,
                'lat_origen' => $user->lat_origen,
                'lng_origen' => $user->lng_origen,
                'ccaa_preferidas' => $user->ccaa_preferidas,
                'notificaciones_email' => (bool) $user->notificaciones_email,
                'plan' => $user->plan,
                'plan_status' => $user->plan_status,
                'consentimiento_aceptado_en' => $user->terms_accepted_at?->toIso8601String(),
                'alta_en' => $user->created_at?->toIso8601String(),
            ],
            'especialidades' => $user->especialidades->map(fn ($e) => [
                'especialidad' => $e->specialty?->name,
                'codigo' => $e->specialty?->code,
                'posicion_bolsa' => $e->posicion_bolsa,
                'estado_bolsa' => $e->estado_bolsa,
                'anyo' => $e->anyo,
            ])->all(),
            'historial' => $user->historial->map(fn ($h) => [
                'anyo' => $h->anyo,
                'especialidad' => $h->specialty?->name,
                'proceso' => $h->proceso?->nombre,
                'estado' => $h->estado,
                'posicion_definitiva' => $h->posicion_definitiva,
                'centro' => $h->centroAdjudicado?->nombre,
            ])->all(),
            'documentos' => UserDocument::where('user_id', $user->id)->get()
                ->map(fn ($d) => [
                    'nombre' => $d->name,
                    'tipo' => $d->type,
                    'tamano_bytes' => $d->size_bytes,
                    'origen' => $d->source,
                    'subido_en' => $d->created_at?->toIso8601String(),
                ])->all(),
            'conversaciones_ia' => AiConversation::where('user_id', $user->id)
                ->with('messages')
                ->get()
                ->map(fn ($c) => [
                    'titulo' => $c->title,
                    'modo' => $c->mode,
                    'creada_en' => $c->created_at?->toIso8601String(),
                    'mensajes' => $c->messages->map(fn ($m) => [
                        'rol' => $m->role,
                        'contenido' => $m->content,
                        'fecha' => $m->created_at?->toIso8601String(),
                    ])->all(),
                ])->all(),
            'integraciones' => UserIntegration::where('user_id', $user->id)
                ->get()
                // Never export the OAuth tokens themselves — only which
                // providers are connected.
                ->map(fn ($i) => [
                    'proveedor' => $i->provider,
                    'conectado_en' => $i->created_at?->toIso8601String(),
                ])->all(),
            'valoraciones_centros' => $user->valoraciones()->get()->map(fn ($v) => [
                'centro_id' => $v->centro_id,
                'curso_escolar' => $v->curso_escolar,
                'comentario' => $v->comentario ?? null,
                'fecha' => $v->created_at?->toIso8601String(),
            ])->all(),
            'anuncios_tablon' => $user->anuncios()->get()->map(fn ($a) => [
                'titulo' => $a->titulo ?? null,
                'fecha' => $a->created_at?->toIso8601String(),
            ])->all(),
            'suscripciones' => $user->suscripciones()->get()->map(fn ($s) => [
                'plan' => $s->plan ?? null,
                'estado' => $s->estado ?? null,
                'fecha' => $s->created_at?->toIso8601String(),
            ])->all(),
        ];

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="doccentia-mis-datos.json"',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Permanently delete the account and everything attached to it (RGPD
     * art. 17). Requires the user to type a confirmation word so it can't fire
     * by accident; the bearer token already proves who is asking.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'confirmacion' => ['required', 'string', 'in:ELIMINAR'],
        ], [
            'confirmacion.in' => 'Escribe ELIMINAR para confirmar el borrado de tu cuenta.',
        ]);

        $user = $request->user();

        // Stored files live on disk, not in the DB, so the cascade won't reach
        // them — delete them explicitly first.
        $disk = Storage::disk(config('documents.disk'));
        UserDocument::where('user_id', $user->id)
            ->get(['disk_path', 'thumbnail_path'])
            ->each(function (UserDocument $doc) use ($disk) {
                $disk->delete(array_filter([$doc->disk_path, $doc->thumbnail_path]));
            });

        DB::transaction(function () use ($user) {
            // Revoke every issued token (polymorphic table, no FK cascade).
            $user->tokens()->delete();
            // The remaining related rows cascade on the users FK.
            $user->delete();
        });

        return response()->json(['deleted' => true]);
    }
}
