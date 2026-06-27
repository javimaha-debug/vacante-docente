<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\TablonContactoMail;
use App\Mail\TablonRespuestaMail;
use App\Models\Ccaa;
use App\Models\TablonAnuncio;
use App\Models\TablonContacto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class TablonController extends Controller
{
    /**
     * Public board listing. Never exposes contacto_email.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categoria' => ['sometimes', 'in:coche,alojamiento,centro,general'],
            'ccaa_id' => ['sometimes', 'integer', 'exists:ccaas,id'],
            'localidad_origen' => ['sometimes', 'nullable', 'string', 'max:100'],
            'localidad_destino' => ['sometimes', 'nullable', 'string', 'max:100'],
            'specialty_id' => ['sometimes', 'integer', 'exists:specialties,id'],
        ]);

        $query = TablonAnuncio::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest();

        foreach (['categoria', 'ccaa_id', 'localidad_origen', 'localidad_destino', 'specialty_id'] as $f) {
            if (! empty($data[$f])) {
                $f === 'localidad_origen' || $f === 'localidad_destino'
                    ? $query->where($f, 'like', '%'.$data[$f].'%')
                    : $query->where($f, $data[$f]);
            }
        }

        $paginator = $query->paginate(20)->withQueryString();
        $paginator->getCollection()->transform(fn (TablonAnuncio $a) => $this->publicArray($a));

        return response()->json($paginator);
    }

    /**
     * Create an announcement (auth). 60-day expiry; ccaa from the user profile.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categoria' => ['required', 'in:coche,alojamiento,centro,general'],
            'titulo' => ['required', 'string', 'max:200'],
            'contenido' => ['required', 'string'],
            'localidad_origen' => ['nullable', 'string', 'max:100'],
            'localidad_destino' => ['nullable', 'string', 'max:100'],
            'centro_id' => ['nullable', 'integer', 'exists:centros,id'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'contacto_email' => ['nullable', 'email', 'max:100'],
        ]);

        $user = $request->user();
        $ccaaId = $user->ccaa_id ?? Ccaa::where('code', 'CV')->value('id');

        $anuncio = TablonAnuncio::create([
            ...$data,
            'user_id' => $user->id,
            'ccaa_id' => $ccaaId,
            'expires_at' => Carbon::now()->addDays(60),
            'is_active' => true,
        ]);

        return response()->json($this->publicArray($anuncio), 201);
    }

    /**
     * Soft-delete an announcement (owner only).
     */
    public function destroy(Request $request, TablonAnuncio $anuncio): JsonResponse
    {
        if ($anuncio->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $anuncio->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Send a contact request to the announcement owner (auth). Owner's email is
     * never revealed to the requester; the owner gets a tokenized reply link.
     */
    public function contactar(Request $request, TablonAnuncio $anuncio): JsonResponse
    {
        $data = $request->validate([
            'mensaje' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        if ($anuncio->user_id === $user->id) {
            return response()->json(['message' => 'No puedes contactar con tu propio anuncio.'], 422);
        }

        $contacto = TablonContacto::create([
            'anuncio_id' => $anuncio->id,
            'user_id' => $user->id,
            'mensaje' => $data['mensaje'],
        ]);

        $replyUrl = URL::temporarySignedRoute(
            'tablon.responder',
            now()->addDays(7),
            ['contacto' => $contacto->id]
        );

        if ($anuncio->user?->email) {
            Mail::to($anuncio->user->email)->queue(new TablonContactoMail($anuncio, $data['mensaje'], $replyUrl));
            $contacto->update(['email_enviado' => true]);
        }

        return response()->json(['message' => 'Mensaje enviado.', 'contacto_id' => $contacto->id], 201);
    }

    /**
     * The owner replies to a contact request; the reply is emailed to the
     * requester. Owner authenticated + must own the announcement.
     */
    public function responder(Request $request, TablonContacto $contacto): JsonResponse
    {
        $data = $request->validate([
            'mensaje' => ['required', 'string', 'max:2000'],
        ]);

        $contacto->load(['anuncio', 'user']);

        if (! $contacto->anuncio || $contacto->anuncio->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($contacto->user?->email) {
            Mail::to($contacto->user->email)->queue(new TablonRespuestaMail($contacto->anuncio, $data['mensaje']));
        }

        $contacto->update(['leido' => true]);

        return response()->json(['message' => 'Respuesta enviada.']);
    }

    /**
     * The user's own announcements with contact-request counts.
     */
    public function misAnuncios(Request $request): JsonResponse
    {
        $anuncios = TablonAnuncio::query()
            ->where('user_id', $request->user()->id)
            ->withCount('contactos')
            ->latest()
            ->get()
            ->map(fn (TablonAnuncio $a) => $this->publicArray($a) + [
                'contactos_count' => $a->contactos_count,
                'expires_at' => $a->expires_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $anuncios]);
    }

    /**
     * Contact requests for one of the user's own announcements (message + date
     * only; requester identity is not revealed).
     */
    public function contactos(Request $request, TablonAnuncio $anuncio): JsonResponse
    {
        if ($anuncio->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $contactos = $anuncio->contactos()->latest()->get()
            ->map(fn (TablonContacto $c) => [
                'id' => $c->id,
                'mensaje' => $c->mensaje,
                'fecha' => $c->created_at?->toIso8601String(),
                'leido' => (bool) $c->leido,
            ]);

        return response()->json(['data' => $contactos]);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicArray(TablonAnuncio $a): array
    {
        return [
            'id' => $a->id,
            'categoria' => $a->categoria,
            'titulo' => $a->titulo,
            'contenido' => $a->contenido,
            'localidad_origen' => $a->localidad_origen,
            'localidad_destino' => $a->localidad_destino,
            'centro_id' => $a->centro_id,
            'specialty_id' => $a->specialty_id,
            'ccaa_id' => $a->ccaa_id,
            'created_at' => $a->created_at?->toIso8601String(),
            // contacto_email is intentionally never serialized in listings.
        ];
    }
}
