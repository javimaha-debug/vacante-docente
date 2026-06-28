<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\MetricaDiaria;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Approximate monthly price per plan, used to estimate MRR. */
    private const PRECIO_MENSUAL = [
        'interino' => 4.99,
        'opositor' => 9.99,
        'docente_pro' => 7.99,
        'todo_en_uno' => 14.99,
    ];

    /**
     * Headline KPIs + recent metrics for the super-admin dashboard.
     */
    public function index(): JsonResponse
    {
        $now = Carbon::now();
        $hoy = $now->copy()->startOfDay();
        $hace7 = $now->copy()->subDays(7);
        $hace30 = $now->copy()->subDays(30);

        $total = User::count();
        $free = User::where('plan', 'free')->count();
        $dePago = User::where('plan', '!=', 'free')->where('plan_status', 'active')->count();
        $activos7d = User::where('last_active_at', '>=', $hace7)->count();
        $nuevosHoy = User::where('created_at', '>=', $hoy)->count();
        $nuevos7d = User::where('created_at', '>=', $hace7)->count();
        $nuevos30d = User::where('created_at', '>=', $hace30)->count();
        $suspendidos = User::whereNotNull('suspended_at')->count();

        $porPlan = User::select('plan', DB::raw('count(*) as total'))
            ->groupBy('plan')
            ->pluck('total', 'plan');

        // Estimated MRR from active paid subscriptions.
        $mrr = 0.0;
        foreach (self::PRECIO_MENSUAL as $plan => $precio) {
            $count = User::where('plan', $plan)->where('plan_status', 'active')->count();
            $mrr += $count * $precio;
        }

        $serie = MetricaDiaria::orderByDesc('fecha')->limit(30)->get()
            ->sortBy('fecha')->values()
            ->map(fn (MetricaDiaria $m) => [
                'fecha' => $m->fecha?->toDateString(),
                'usuarios_total' => $m->usuarios_total,
                'usuarios_nuevos' => $m->usuarios_nuevos,
                'usuarios_activos_7d' => $m->usuarios_activos_7d,
                'usuarios_de_pago' => $m->usuarios_de_pago,
                'mrr' => (float) $m->mrr,
            ]);

        $ultimosRegistros = User::orderByDesc('created_at')->limit(8)->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'plan' => $u->plan,
                'created_at' => $u->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'kpis' => [
                'usuarios_total' => $total,
                'usuarios_free' => $free,
                'usuarios_de_pago' => $dePago,
                'usuarios_activos_7d' => $activos7d,
                'usuarios_suspendidos' => $suspendidos,
                'nuevos_hoy' => $nuevosHoy,
                'nuevos_7d' => $nuevos7d,
                'nuevos_30d' => $nuevos30d,
                'mrr' => round($mrr, 2),
                'arr' => round($mrr * 12, 2),
                'conversion' => $total > 0 ? round($dePago / $total * 100, 1) : 0,
            ],
            'por_plan' => $porPlan,
            'serie' => $serie,
            'ultimos_registros' => $ultimosRegistros,
        ]);
    }
}
