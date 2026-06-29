<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ImportListadoManual;
use App\Models\GvaNoticia;
use App\Models\Proceso;
use App\Services\GvaAutoImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class GvaController extends Controller
{
    /**
     * Hosts the manual-import fetch is allowed to reach (anti-SSRF). Only
     * official gazette / education domains a real listing could live on.
     */
    private const ALLOWED_IMPORT_HOSTS = [
        'ceice.gva.es',
        'dogv.gva.es',
        'www.dogv.gva.es',
        'boe.es',
        'www.boe.es',
        'anpecomunidadvalenciana.es',
    ];

    /**
     * Reject URLs whose host isn't on the allow-list (anti-SSRF). The host is
     * matched exactly (case-insensitive) against ALLOWED_IMPORT_HOSTS.
     */
    private function assertAllowedUrl(string $url): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        abort_unless(
            $host !== '' && in_array($host, self::ALLOWED_IMPORT_HOSTS, true),
            422,
            'El dominio de la URL no está permitido para importación.'
        );
    }

    /**
     * Auto-import dashboard: detected listing PDFs with their import outcome.
     */
    public function adminImportaciones(Request $request): JsonResponse
    {
        $noticias = GvaNoticia::query()
            ->with('proceso:id,nombre')
            ->where('tipo', 'PDF')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (GvaNoticia $n) => [
                'id' => $n->id,
                'titulo' => $n->titulo,
                'url' => $n->url,
                'fecha_publicacion' => $n->fecha_publicacion?->toDateString(),
                'importado_en' => $n->importado_en?->toIso8601String(),
                'import_estado' => $n->import_estado,
                'import_resumen' => $n->import_resumen,
                'proceso' => $n->proceso ? ['id' => $n->proceso->id, 'nombre' => $n->proceso->nombre] : null,
            ]);

        return response()->json(['data' => $noticias]);
    }

    /**
     * Create the procesos of a course (e.g. a past year) from the admin UI, so
     * historical listings can then be imported into them.
     */
    public function adminCrearProcesos(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anyo' => ['required', 'integer', 'min:2000', 'max:2100'],
            'estado' => ['sometimes', 'in:publicado,pendiente,cerrado'],
        ]);

        Artisan::call('procesos:create', [
            'anyo' => $data['anyo'],
            '--estado' => $data['estado'] ?? 'cerrado',
        ]);

        $procesos = Proceso::where('anyo', $data['anyo'])
            ->with('colectivo:id,code,name,body')
            ->orderBy('id')
            ->get(['id', 'nombre', 'anyo', 'colectivo_id'])
            ->map(fn (Proceso $p) => ['id' => $p->id, 'nombre' => $p->nombre]);

        return response()->json(['data' => $procesos, 'output' => trim(Artisan::output())]);
    }

    /**
     * Queue a manual/historical listing import from a URL into a chosen proceso.
     * Heavy imports run in the background; the result shows in the list above.
     */
    public function adminImportarManual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:1000'],
            'tipo' => ['required', 'in:vacantes,participantes,continua'],
            'proceso_id' => ['required_if:tipo,vacantes,participantes', 'nullable', 'integer', 'exists:procesos,id'],
        ]);

        // Anti-SSRF: only allow fetching from official gazette/education hosts.
        $this->assertAllowedUrl($data['url']);

        // Track it as a notice so its status appears in the admin list.
        $noticia = GvaNoticia::firstOrCreate(
            ['url' => $data['url']],
            ['titulo' => 'Importación manual: '.basename(parse_url($data['url'], PHP_URL_PATH) ?: $data['url']), 'tipo' => 'PDF'],
        );
        $noticia->forceFill(['import_estado' => null, 'import_resumen' => 'En cola…'])->save();

        ImportListadoManual::dispatch($noticia->id, $data['tipo'], $data['proceso_id'] ?? null);

        return response()->json(['queued' => true, 'noticia_id' => $noticia->id], 202);
    }

    /**
     * Re-run the import for a notice. Optionally force a target proceso + kind
     * (for items the heuristic couldn't map automatically).
     */
    public function adminReimport(Request $request, GvaNoticia $noticia, GvaAutoImportService $service): JsonResponse
    {
        $data = $request->validate([
            'proceso_id' => ['sometimes', 'nullable', 'integer', 'exists:procesos,id'],
            'kind' => ['sometimes', 'nullable', 'in:participantes,vacantes'],
        ]);

        if (! empty($data['proceso_id'])) {
            $proceso = Proceso::findOrFail($data['proceso_id']);
            $resumen = $service->importInto($noticia, $data['kind'] ?? '', $proceso);
        } else {
            $resumen = $service->import($noticia);
        }

        $noticia->refresh()->load('proceso:id,nombre');

        return response()->json([
            'resumen' => $resumen,
            'import_estado' => $noticia->import_estado,
            'importado_en' => $noticia->importado_en?->toIso8601String(),
            'proceso' => $noticia->proceso ? ['id' => $noticia->proceso->id, 'nombre' => $noticia->proceso->nombre] : null,
        ]);
    }

    /**
     * Latest official GVA notices (public).
     */
    public function index(): JsonResponse
    {
        $noticias = GvaNoticia::query()
            ->orderByDesc('fecha_publicacion')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'titulo', 'url', 'fecha_publicacion', 'tipo', 'resumen']);

        return response()->json(['data' => $noticias]);
    }

    /**
     * Unnotified GVA items for an admin to review and trigger imports.
     * Authorization is enforced by the EnsureSuperAdmin route middleware.
     */
    public function adminUnnotified(Request $request): JsonResponse
    {
        $noticias = GvaNoticia::query()
            ->where('notificado', false)
            ->orderByDesc('id')
            ->get(['id', 'titulo', 'url', 'fecha_publicacion', 'tipo', 'resumen', 'keywords_matched', 'notificado']);

        return response()->json(['data' => $noticias]);
    }
}
