<?php

namespace App\Console\Commands;

use App\Models\MetricaDiaria;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CalcularMetricas extends Command
{
    protected $signature = 'metricas:calcular {fecha? : Día a calcular (Y-m-d), por defecto hoy}';

    protected $description = 'Calcula y almacena las métricas diarias del SaaS (usuarios, MRR, churn).';

    /** Approximate monthly price per paid plan, used to estimate MRR. */
    private const PRECIO_MENSUAL = [
        'interino' => 4.99,
        'opositor' => 9.99,
        'docente_pro' => 7.99,
        'todo_en_uno' => 14.99,
    ];

    public function handle(): int
    {
        $fecha = $this->argument('fecha')
            ? Carbon::parse($this->argument('fecha'))->startOfDay()
            : Carbon::today();

        $finDia = $fecha->copy()->endOfDay();
        $inicio7 = $fecha->copy()->subDays(7);

        $total = User::where('created_at', '<=', $finDia)->count();
        $nuevos = User::whereBetween('created_at', [$fecha->copy()->startOfDay(), $finDia])->count();
        $activos7d = User::where('last_active_at', '>=', $inicio7)->count();
        $free = User::where('plan', 'free')->count();
        $dePago = User::where('plan', '!=', 'free')->where('plan_status', 'active')->count();

        $nuevosPorPlan = [];
        foreach (['interino', 'opositor', 'docente_pro', 'todo_en_uno'] as $plan) {
            $nuevosPorPlan[$plan] = User::where('plan', $plan)
                ->whereBetween('created_at', [$fecha->copy()->startOfDay(), $finDia])
                ->count();
        }

        $mrr = 0.0;
        foreach (self::PRECIO_MENSUAL as $plan => $precio) {
            $mrr += User::where('plan', $plan)->where('plan_status', 'active')->count() * $precio;
        }

        // Churn: subscriptions canceled on this day.
        $churnCount = User::where('plan_status', 'canceled')
            ->whereBetween('updated_at', [$fecha->copy()->startOfDay(), $finDia])
            ->count();

        $metrica = MetricaDiaria::updateOrCreate(
            ['fecha' => $fecha->toDateString()],
            [
                'usuarios_total' => $total,
                'usuarios_nuevos' => $nuevos,
                'usuarios_activos_7d' => $activos7d,
                'usuarios_free' => $free,
                'usuarios_de_pago' => $dePago,
                'mrr' => round($mrr, 2),
                'arr' => round($mrr * 12, 2),
                'nuevos_interino' => $nuevosPorPlan['interino'],
                'nuevos_opositor' => $nuevosPorPlan['opositor'],
                'nuevos_docente_pro' => $nuevosPorPlan['docente_pro'],
                'nuevos_todo_en_uno' => $nuevosPorPlan['todo_en_uno'],
                'churn_count' => $churnCount,
                'churn_mrr' => 0,
            ],
        );

        $this->info("Métricas calculadas para {$metrica->fecha->toDateString()}: ".
            "{$total} usuarios, {$dePago} de pago, MRR ".round($mrr, 2)."€.");

        return self::SUCCESS;
    }
}
