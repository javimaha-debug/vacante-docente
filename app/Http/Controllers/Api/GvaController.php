<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GvaNoticia;
use App\Models\Proceso;
use App\Services\GvaAutoImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GvaController extends Controller
{
    private function denyUnlessAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();

        return ($user->id === 1 || $user->is_admin)
            ? null
            : response()->json(['message' => 'No autorizado.'], 403);
    }

    /**
     * Auto-import dashboard: detected listing PDFs with their import outcome.
     */
    public function adminImportaciones(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessAdmin($request)) {
            return $deny;
        }

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
        if ($deny = $this->denyUnlessAdmin($request)) {
            return $deny;
        }

        $data = $request->validate([
            'anyo' => ['required', 'integer', 'min:2000', 'max:2100'],
            'estado' => ['sometimes', 'in:publicado,pendiente,cerrado'],
        ]);

        \Illuminate\Support\Facades\Artisan::call('procesos:create', [
            'anyo' => $data['anyo'],
            '--estado' => $data['estado'] ?? 'cerrado',
        ]);

        $procesos = Proceso::where('anyo', $data['anyo'])
            ->with('colectivo:id,code,name,body')
            ->orderBy('id')
            ->get(['id', 'nombre', 'anyo', 'colectivo_id'])
            ->map(fn (Proceso $p) => ['id' => $p->id, 'nombre' => $p->nombre]);

        return response()->json(['data' => $procesos, 'output' => trim(\Illuminate\Support\Facades\Artisan::output())]);
    }

    /**
     * Queue a manual/historical listing import from a URL into a chosen proceso.
     * Heavy imports run in the background; the result shows in the list above.
     */
    public function adminImportarManual(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessAdmin($request)) {
            return $deny;
        }

        $data = $request->validate([
            'url' => ['required', 'url', 'max:1000'],
            'tipo' => ['required', 'in:vacantes,participantes,continua'],
            'proceso_id' => ['required_if:tipo,vacantes,participantes', 'nullable', 'integer', 'exists:procesos,id'],
        ]);

        // Track it as a notice so its status appears in the admin list.
        $noticia = GvaNoticia::firstOrCreate(
            ['url' => $data['url']],
            ['titulo' => 'Importación manual: '.basename(parse_url($data['url'], PHP_URL_PATH) ?: $data['url']), 'tipo' => 'PDF'],
        );
        $noticia->forceFill(['import_estado' => null, 'import_resumen' => 'En cola…'])->save();

        \App\Jobs\ImportListadoManual::dispatch($noticia->id, $data['tipo'], $data['proceso_id'] ?? null);

        return response()->json(['queued' => true, 'noticia_id' => $noticia->id], 202);
    }

    /**
     * Re-run the import for a notice. Optionally force a target proceso + kind
     * (for items the heuristic couldn't map automatically).
     */
    public function adminReimport(Request $request, GvaNoticia $noticia, GvaAutoImportService $service): JsonResponse
    {
        if ($deny = $this->denyUnlessAdmin($request)) {
            return $deny;
        }

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
     * Restricted to user id=1 or users flagged is_admin.
     */
    public function adminUnnotified(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! ($user->id === 1 || $user->is_admin)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $noticias = GvaNoticia::query()
            ->where('notificado', false)
            ->orderByDesc('id')
            ->get(['id', 'titulo', 'url', 'fecha_publicacion', 'tipo', 'resumen', 'keywords_matched', 'notificado']);

        return response()->json(['data' => $noticias]);
    }
}
