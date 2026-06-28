<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Suscripcion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuscripcionesController extends Controller
{
    /**
     * Paginated, filterable list of subscriptions.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'plan' => ['sometimes', 'nullable', 'string', 'max:50'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'per_page' => ['sometimes', 'integer', 'min:5', 'max:100'],
        ]);

        $page = Suscripcion::query()
            ->with('user:id,name,email')
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['plan'] ?? null, fn ($q, $p) => $q->where('plan_codigo', $p))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->whereHas('user', fn ($w) => $w
                ->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->orderByDesc('created_at')
            ->paginate($data['per_page'] ?? 25);

        return response()->json([
            'data' => collect($page->items())->map(fn (Suscripcion $s) => [
                'id' => $s->id,
                'usuario' => $s->user?->name,
                'email' => $s->user?->email,
                'user_id' => $s->user_id,
                'plan_codigo' => $s->plan_codigo,
                'status' => $s->status,
                'stripe_subscription_id' => $s->stripe_subscription_id,
                'current_period_end' => $s->current_period_end?->toIso8601String(),
                'cancel_at_period_end' => $s->cancel_at_period_end,
                'created_at' => $s->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
