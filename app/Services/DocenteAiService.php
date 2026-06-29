<?php

namespace App\Services;

use App\Models\AiUsage;
use App\Models\CurriculoContenido;
use App\Models\DocenteAsignatura;
use App\Models\DocenteMerito;
use App\Models\DocenteProgramacion;
use App\Models\DocenteUnidad;
use Illuminate\Support\Carbon;

class DocenteAiService
{
    private const BAREMO = [
        'formacion' => ['max' => 10.0, 'por_hora' => 0.1, 'max_horas_curso' => 200],
        'publicacion' => ['max' => 3.0, 'por_item' => 1.0],
        'cargo' => ['max' => 4.0, 'por_anyo' => 1.0],
        'actividad_complementaria' => ['max' => 3.0, 'por_item' => 0.5],
        'otro' => ['max' => 3.0, 'por_item' => 0.25],
    ];

    public function __construct(
        private readonly AnthropicService $anthropic,
        private readonly RagService $rag,
    ) {}

    // ──────────────────────────────────────────────
    // ACTIVIDAD
    // ──────────────────────────────────────────────
    public function generateActividad(int $userId, int $unidadId, array $params): array
    {
        $unidad = DocenteUnidad::with('programacion.asignatura')->findOrFail($unidadId);
        $asignatura = $unidad->programacion->asignatura;
        $competencias = implode(', ', $unidad->competencias ?? []);

        $curriculo = CurriculoContenido::where('asignatura', 'LIKE', '%' . $asignatura->nombre . '%')
            ->where('curso', 'LIKE', '%' . $asignatura->curso . '%')
            ->limit(3)->get()
            ->pluck('contenido')->implode(' | ');

        $prompt = "Eres un experto en didáctica. Genera una actividad para {$asignatura->nombre}, {$asignatura->curso}, UD: {$unidad->titulo}."
            . " Tipo: {$params['tipo']}. Duración: {$params['tiempo_minutos']} min."
            . " Debe trabajar las competencias: {$competencias}."
            . ($curriculo ? " Currículo oficial aplicable: {$curriculo}." : '')
            . " Responde SOLO con JSON válido (sin texto extra): {\"titulo\":\"\",\"descripcion\":\"\",\"desarrollo_paso_a_paso\":[],\"materiales\":[],\"evaluacion\":\"\"}";

        $result = $this->anthropic->chat(
            [['role' => 'user', 'content' => $prompt]],
            'Eres un asistente educativo especializado en didáctica y pedagogía.',
            800
        );

        $this->recordUsage($userId, $result);

        return ['output' => $this->parseJson($result['text']), 'tokens' => $result['tokens_output']];
    }

    // ──────────────────────────────────────────────
    // RÚBRICA
    // ──────────────────────────────────────────────
    public function generateRubrica(int $userId, array $params): array
    {
        $criteriosOficiales = CurriculoContenido::where('asignatura', 'LIKE', "%{$params['asignatura']}%")
            ->where('curso', 'LIKE', "%{$params['curso']}%")
            ->limit(5)->get()
            ->flatMap(fn ($c) => $c->criterios_evaluacion ?? [])
            ->unique()->implode(' | ');

        $competencias = implode(', ', $params['competencias'] ?? []);
        $numCriterios = $params['num_criterios'] ?? 4;

        $prompt = "Genera una rúbrica de evaluación para {$params['tipo_tarea']} en {$params['asignatura']} {$params['curso']}."
            . " Competencias: {$competencias}."
            . ($criteriosOficiales ? " Criterios de evaluación LOMLOE aplicables: {$criteriosOficiales}." : '')
            . " Genera exactamente {$numCriterios} criterios."
            . " Responde SOLO con JSON válido: [{\"nombre_criterio\":\"\",\"descripcion\":\"\",\"niveles\":[{\"nivel\":\"\",\"descriptor\":\"\",\"puntos\":0}]}]";

        $result = $this->anthropic->chat(
            [['role' => 'user', 'content' => $prompt]],
            'Eres un experto en evaluación educativa y diseño de rúbricas LOMLOE.',
            1200
        );

        $this->recordUsage($userId, $result);

        return ['criterios' => $this->parseJson($result['text']), 'tokens' => $result['tokens_output']];
    }

    // ──────────────────────────────────────────────
    // SITUACIÓN DE APRENDIZAJE
    // ──────────────────────────────────────────────
    public function generateSituacionAprendizaje(int $userId, array $params): array
    {
        $competencias = implode(', ', $params['competencias_clave'] ?? []);
        $numSesiones = $params['num_sesiones'] ?? 4;

        $prompt = "Diseña una situación de aprendizaje para {$params['asignatura']}, {$params['curso']}."
            . " Competencias clave a trabajar: {$competencias}."
            . " Parte de este contexto del mundo real: {$params['contexto_mundo_real']}."
            . " Duración: {$numSesiones} sesiones."
            . " Responde SOLO con JSON válido: {\"titulo\":\"\",\"descripcion\":\"\",\"contexto\":\"\",\"actividades\":[{\"titulo\":\"\",\"descripcion\":\"\",\"tiempo_min\":0,\"agrupamiento\":\"\",\"recursos\":[]}],\"criterios_evaluacion\":[],\"instrumentos_evaluacion\":[]}";

        $result = $this->anthropic->chat(
            [['role' => 'user', 'content' => $prompt]],
            'Eres un experto en diseño de situaciones de aprendizaje según el marco LOMLOE.',
            1500
        );

        $this->recordUsage($userId, $result);

        return ['situacion' => $this->parseJson($result['text']), 'tokens' => $result['tokens_output']];
    }

    // ──────────────────────────────────────────────
    // EXAMEN
    // ──────────────────────────────────────────────
    public function generateExamen(int $userId, array $params): array
    {
        $asignatura = isset($params['asignatura_id'])
            ? DocenteAsignatura::find($params['asignatura_id'])
            : null;

        $unidad = isset($params['unidad_id'])
            ? DocenteUnidad::find($params['unidad_id'])
            : null;

        $contexto = $asignatura ? "{$asignatura->nombre}, {$asignatura->curso}" : ($params['asignatura'] ?? 'la asignatura');
        $udTitulo = $unidad ? $unidad->titulo : ($params['unidad'] ?? 'los contenidos del período');
        $num = $params['num_preguntas'] ?? 10;
        $tiempo = $params['tiempo_minutos'] ?? 60;
        $tipo = $params['tipo'] ?? 'mixto';
        $dificultad = $params['dificultad'] ?? 'media';

        $prompt = "Genera un examen de tipo {$tipo} para {$contexto}, UD/temática: {$udTitulo}."
            . " Número de preguntas: {$num}. Tiempo: {$tiempo} min. Dificultad: {$dificultad}."
            . " Responde SOLO con JSON válido: {\"titulo\":\"\",\"instrucciones\":\"\",\"preguntas\":[{\"tipo\":\"\",\"enunciado\":\"\",\"opciones\":[],\"respuesta_correcta\":\"\",\"puntos\":0,\"explicacion\":\"\"}]}";

        $result = $this->anthropic->chat(
            [['role' => 'user', 'content' => $prompt]],
            'Eres un experto en evaluación educativa. Genera preguntas claras y bien formuladas.',
            2000
        );

        $this->recordUsage($userId, $result);

        return ['examen' => $this->parseJson($result['text']), 'tokens' => $result['tokens_output']];
    }

    // ──────────────────────────────────────────────
    // ADAPTAR TEXTO
    // ──────────────────────────────────────────────
    public function adaptarTexto(int $userId, string $texto, array $params): array
    {
        $words = str_word_count($texto);
        if ($words > 5000) {
            throw new \InvalidArgumentException('El texto supera el límite de 5.000 palabras.');
        }

        $prompt = "Adapta este texto educativo de nivel {$params['nivel_original']} a {$params['nivel_destino']}."
            . " Tipo de adaptación: {$params['tipo_adaptacion']}."
            . " Mantén el contenido esencial. Texto:\n\n{$texto}\n\nDevuelve SOLO el texto adaptado, sin explicaciones.";

        $result = $this->anthropic->chat(
            [['role' => 'user', 'content' => $prompt]],
            'Eres un experto en adaptación de materiales educativos para distintos niveles.',
            2000
        );

        $this->recordUsage($userId, $result);

        return ['texto_adaptado' => trim($result['text']), 'tokens' => $result['tokens_output']];
    }

    // ──────────────────────────────────────────────
    // CALCULAR MÉRITOS (baremo CV)
    // ──────────────────────────────────────────────
    public function calcularMeritos(int $userId): array
    {
        $meritos = DocenteMerito::where('user_id', $userId)->get();
        $desglose = [];
        $totalGeneral = 0.0;

        foreach (self::BAREMO as $tipo => $regla) {
            $items = $meritos->where('tipo', $tipo);
            $puntos = 0.0;

            if ($tipo === 'formacion') {
                $horas = $items->sum('horas') ?? 0;
                $puntos = min($horas * $regla['por_hora'], $regla['max']);
            } elseif ($tipo === 'cargo') {
                foreach ($items as $m) {
                    $anyos = ($m->fecha_inicio && $m->fecha_fin)
                        ? max(1, Carbon::parse($m->fecha_inicio)->diffInYears(Carbon::parse($m->fecha_fin)))
                        : 1;
                    $puntos += $anyos * $regla['por_anyo'];
                }
                $puntos = min($puntos, $regla['max']);
            } else {
                $puntos = min($items->count() * $regla['por_item'], $regla['max']);
            }

            $desglose[$tipo] = ['puntos' => round($puntos, 3), 'max' => $regla['max']];
            $totalGeneral += $puntos;

            // Update each record with calculated points
            foreach ($items as $m) {
                $individual = $tipo === 'formacion'
                    ? min(($m->horas ?? 0) * $regla['por_hora'], 2.0)
                    : ($regla['por_item'] ?? $regla['por_anyo'] ?? 0);
                $m->update(['puntos_calculados' => $individual]);
            }
        }

        return [
            'total' => round($totalGeneral, 3),
            'desglose' => $desglose,
        ];
    }

    // ──────────────────────────────────────────────
    // ADAPTAR PROGRAMACIÓN A NUEVO CENTRO
    // ──────────────────────────────────────────────
    public function adaptarProgramacion(int $userId, int $programacionId, array $nuevoCentroParams): array
    {
        $programacion = DocenteProgramacion::with('asignatura')->where('user_id', $userId)->findOrFail($programacionId);

        $resumen = [
            'asignatura' => $programacion->asignatura->nombre ?? '',
            'curso' => $programacion->asignatura->curso ?? '',
            'objetivos' => mb_substr($programacion->objetivos_generales ?? '', 0, 500),
            'metodologia' => mb_substr($programacion->metodologia ?? '', 0, 500),
        ];

        $prompt = "Tengo esta programación didáctica de {$resumen['asignatura']}, {$resumen['curso']}:"
            . "\nObjetivos: {$resumen['objetivos']}\nMetodología: {$resumen['metodologia']}"
            . "\n\nMe incorporo a un nuevo centro: {$nuevoCentroParams['nombre']}, tipo: {$nuevoCentroParams['tipo']}"
            . ", bilingüe: " . ($nuevoCentroParams['es_bilingue'] ? 'sí' : 'no') . "."
            . " Sugiere los ajustes necesarios manteniendo la estructura."
            . " Responde con JSON válido: {\"cambios_sugeridos\":[{\"seccion\":\"\",\"cambio_actual\":\"\",\"cambio_sugerido\":\"\",\"motivo\":\"\"}]}";

        $result = $this->anthropic->chat(
            [['role' => 'user', 'content' => $prompt]],
            'Eres un asesor pedagógico especializado en adaptación de programaciones didácticas.',
            1200
        );

        $this->recordUsage($userId, $result);

        return ['sugerencias' => $this->parseJson($result['text']), 'tokens' => $result['tokens_output']];
    }

    // ──────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────
    private function recordUsage(int $userId, array $result): void
    {
        $today = Carbon::now()->toDateString();
        AiUsage::updateOrCreate(
            ['user_id' => $userId, 'date' => $today],
            ['messages_count' => \DB::raw('COALESCE(messages_count,0) + 1'),
             'tokens_input' => \DB::raw('COALESCE(tokens_input,0) + ' . ($result['tokens_input'] ?? 0)),
             'tokens_output' => \DB::raw('COALESCE(tokens_output,0) + ' . ($result['tokens_output'] ?? 0))]
        );
    }

    private function parseJson(string $text): mixed
    {
        // Strip markdown fences if present
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```$/m', '', $text);
        $decoded = json_decode(trim($text), true);

        return $decoded ?? $text;
    }
}
