<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NormativaDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NormativaController extends Controller
{
    /** Disk for normativa PDFs (private; served via signed route only). */
    private const DISK = 'local';

    /**
     * List normativa documents, optionally filtered.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categoria' => ['sometimes', 'nullable', 'in:ley_organica,decreto,orden,resolucion,instrucciones,otro'],
            'comunidad' => ['sometimes', 'nullable', 'string', 'max:100'],
            'especialidad' => ['sometimes', 'nullable', 'string', 'max:50'],
            'cuerpo' => ['sometimes', 'nullable', 'string', 'max:50'],
            'vigente' => ['sometimes', 'nullable', 'boolean'],
            'idioma' => ['sometimes', 'nullable', 'in:castellano,valenciano'],
            'fuente' => ['sometimes', 'nullable', 'in:boe,dogv,manual'],
        ]);

        $docs = NormativaDocumento::query()
            ->when($data['categoria'] ?? null, fn ($q, $c) => $q->where('categoria', $c))
            ->when($data['comunidad'] ?? null, fn ($q, $c) => $q->where('comunidad_autonoma', $c))
            ->when($data['cuerpo'] ?? null, fn ($q, $c) => $q->where(fn ($w) => $w->where('cuerpo', $c)->orWhereNull('cuerpo')))
            // A specialty filter also keeps the "todas" (null) documents.
            ->when($data['especialidad'] ?? null, fn ($q, $c) => $q->where(fn ($w) => $w->where('especialidad_code', $c)->orWhereNull('especialidad_code')))
            ->when($data['idioma'] ?? null, fn ($q, $i) => $q->where('idioma', $i))
            ->when($data['fuente'] ?? null, fn ($q, $f) => $q->where('fuente', $f))
            ->when(array_key_exists('vigente', $data) && $data['vigente'] !== null, fn ($q) => $q->where('vigente', $data['vigente']))
            ->orderByRaw("CASE categoria WHEN 'ley_organica' THEN 0 WHEN 'decreto' THEN 1 WHEN 'orden' THEN 2 WHEN 'resolucion' THEN 3 WHEN 'instrucciones' THEN 4 ELSE 5 END")
            ->orderByDesc('fecha_publicacion')
            ->orderBy('titulo')
            ->get();

        return response()->json(['data' => $docs->map(fn ($d) => $this->docArray($d))]);
    }

    /**
     * Document detail with resolved links.
     */
    public function show(NormativaDocumento $normativa): JsonResponse
    {
        return response()->json($this->docArray($normativa));
    }

    /**
     * Stream a normativa PDF from the private disk (signed, short-lived URL so
     * the storage path is never exposed publicly).
     */
    public function pdf(NormativaDocumento $normativa): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);
        abort_unless($normativa->pdf_path && $disk->exists($normativa->pdf_path), 404);

        return $disk->response($normativa->pdf_path, null, ['Content-Type' => 'application/pdf']);
    }

    /** Signed, 1-hour URL to stream a document's PDF (null when none). */
    public static function pdfUrl(NormativaDocumento $d): ?string
    {
        return $d->pdf_path
            ? URL::temporarySignedRoute('normativa.pdf', now()->addHour(), ['normativa' => $d->id])
            : null;
    }

    /** @return array<string, mixed> */
    private function docArray(NormativaDocumento $d): array
    {
        return [
            'id' => $d->id,
            'titulo' => $d->titulo,
            'descripcion' => $d->descripcion,
            'categoria' => $d->categoria,
            'comunidad_autonoma' => $d->comunidad_autonoma,
            'especialidad_code' => $d->especialidad_code,
            'cuerpo' => $d->cuerpo,
            'url_oficial' => $d->url_oficial,
            'pdf_url' => self::pdfUrl($d),
            'fecha_publicacion' => $d->fecha_publicacion?->toDateString(),
            'vigente' => (bool) $d->vigente,
            'fuente' => $d->fuente ?? 'manual',
            'idioma' => $d->idioma,
            'actualizado' => $d->updated_at?->toIso8601String(),
        ];
    }
}
