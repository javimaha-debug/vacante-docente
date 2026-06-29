<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AiUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AiUsageController extends Controller
{
    // Reference prices (per 1M tokens), June 2026.
    private const PRICE_SONNET_IN = 3.0;
    private const PRICE_SONNET_OUT = 15.0;

    public function index(Request $request): JsonResponse
    {
        $today = Carbon::now()->toDateString();
        $weekAgo = Carbon::now()->subDays(7)->toDateString();
        $monthAgo = Carbon::now()->subDays(30)->toDateString();

        $sum = fn ($since) => AiUsage::where('date', '>=', $since)
            ->selectRaw('COALESCE(SUM(messages_count),0) m, COALESCE(SUM(tokens_input),0) ti, COALESCE(SUM(tokens_output),0) to_, COALESCE(SUM(voyage_calls),0) vc')
            ->first();

        $todayRow = AiUsage::where('date', $today)
            ->selectRaw('COALESCE(SUM(messages_count),0) m')->first();
        $week = $sum($weekAgo);
        $month = $sum($monthAgo);

        $anthropicCost = (((int) $month->ti) / 1_000_000 * self::PRICE_SONNET_IN)
            + (((int) $month->to_) / 1_000_000 * self::PRICE_SONNET_OUT);

        $top = AiUsage::where('date', '>=', $monthAgo)
            ->select('user_id', DB::raw('SUM(messages_count) as messages'), DB::raw('SUM(tokens_input+tokens_output) as tokens'))
            ->groupBy('user_id')->orderByDesc('messages')->limit(10)
            ->with('user:id,name,email')->get()
            ->map(fn ($r) => [
                'user_id' => $r->user_id,
                'name' => $r->user?->name,
                'email' => $r->user?->email,
                'messages' => (int) $r->messages,
                'tokens' => (int) $r->tokens,
            ]);

        $series = AiUsage::where('date', '>=', $monthAgo)
            ->select('date', DB::raw('SUM(messages_count) as messages'), DB::raw('SUM(tokens_input+tokens_output) as tokens'), DB::raw('SUM(voyage_calls) as voyage'))
            ->groupBy('date')->orderBy('date')->get()
            ->map(fn ($r) => [
                'date' => (string) $r->date,
                'messages' => (int) $r->messages,
                'tokens' => (int) $r->tokens,
                'voyage' => (int) $r->voyage,
            ]);

        return response()->json([
            'mensajes' => [
                'hoy' => (int) ($todayRow->m ?? 0),
                'semana' => (int) $week->m,
                'mes' => (int) $month->m,
            ],
            'voyage_calls_mes' => (int) $month->vc,
            'tokens_mes' => ['input' => (int) $month->ti, 'output' => (int) $month->to_],
            'coste_estimado_usd' => round($anthropicCost, 4),
            'top_usuarios' => $top,
            'serie_diaria' => $series,
        ]);
    }
}
