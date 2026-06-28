<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\MetricaDiaria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetricasController extends Controller
{
    /**
     * Daily metrics time series (most recent `dias` days, oldest first).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dias' => ['sometimes', 'integer', 'min:7', 'max:365'],
        ]);

        $metricas = MetricaDiaria::orderByDesc('fecha')
            ->limit($data['dias'] ?? 90)
            ->get()
            ->sortBy('fecha')
            ->values()
            ->map(fn (MetricaDiaria $m) => $this->serialize($m));

        return response()->json(['data' => $metricas]);
    }

    /**
     * Export the full daily metrics table as CSV.
     */
    public function export(): StreamedResponse
    {
        $columns = [
            'fecha', 'usuarios_total', 'usuarios_nuevos', 'usuarios_activos_7d',
            'usuarios_free', 'usuarios_de_pago', 'mrr', 'arr', 'nuevos_interino',
            'nuevos_opositor', 'nuevos_docente_pro', 'nuevos_todo_en_uno',
            'churn_count', 'churn_mrr',
        ];

        return response()->streamDownload(function () use ($columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            MetricaDiaria::orderBy('fecha')->chunk(500, function ($chunk) use ($out, $columns) {
                foreach ($chunk as $m) {
                    $row = [];
                    foreach ($columns as $col) {
                        $row[] = $col === 'fecha' ? $m->fecha?->toDateString() : $m->{$col};
                    }
                    fputcsv($out, $row);
                }
            });

            fclose($out);
        }, 'metricas.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(MetricaDiaria $m): array
    {
        return [
            'fecha' => $m->fecha?->toDateString(),
            'usuarios_total' => $m->usuarios_total,
            'usuarios_nuevos' => $m->usuarios_nuevos,
            'usuarios_activos_7d' => $m->usuarios_activos_7d,
            'usuarios_free' => $m->usuarios_free,
            'usuarios_de_pago' => $m->usuarios_de_pago,
            'mrr' => (float) $m->mrr,
            'arr' => (float) $m->arr,
            'nuevos_interino' => $m->nuevos_interino,
            'nuevos_opositor' => $m->nuevos_opositor,
            'nuevos_docente_pro' => $m->nuevos_docente_pro,
            'nuevos_todo_en_uno' => $m->nuevos_todo_en_uno,
            'churn_count' => $m->churn_count,
            'churn_mrr' => (float) $m->churn_mrr,
        ];
    }
}
