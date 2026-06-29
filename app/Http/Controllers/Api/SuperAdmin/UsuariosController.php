<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AdminNota;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class UsuariosController extends Controller
{
    /**
     * Paginated, filterable user list.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'plan' => ['sometimes', 'nullable', 'string', 'max:50'],
            'plan_status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'role' => ['sometimes', 'nullable', 'in:user,admin,superadmin'],
            'estado' => ['sometimes', 'nullable', 'in:activo,suspendido'],
            'per_page' => ['sometimes', 'integer', 'min:5', 'max:100'],
        ]);

        $query = User::query()
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhere('nombre_gva', 'like', "%{$s}%")))
            ->when($data['plan'] ?? null, fn ($q, $p) => $q->where('plan', $p))
            ->when($data['plan_status'] ?? null, fn ($q, $p) => $q->where('plan_status', $p))
            ->when($data['role'] ?? null, fn ($q, $r) => $q->where('role', $r))
            ->when(($data['estado'] ?? null) === 'suspendido', fn ($q) => $q->whereNotNull('suspended_at'))
            ->when(($data['estado'] ?? null) === 'activo', fn ($q) => $q->whereNull('suspended_at'))
            ->orderByDesc('created_at');

        $page = $query->paginate($data['per_page'] ?? 25);

        return response()->json([
            'data' => collect($page->items())->map(fn (User $u) => $this->row($u)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Full detail for one user, including subscriptions, notes and history.
     */
    public function show(User $usuario): JsonResponse
    {
        $usuario->load(['ccaa', 'colectivo', 'especialidades.specialty']);

        $suscripciones = $usuario->suscripciones()->orderByDesc('created_at')->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'plan_codigo' => $s->plan_codigo,
                'status' => $s->status,
                'stripe_subscription_id' => $s->stripe_subscription_id,
                'current_period_end' => $s->current_period_end?->toIso8601String(),
                'cancel_at_period_end' => $s->cancel_at_period_end,
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        $notas = AdminNota::where('user_id', $usuario->id)
            ->with('admin:id,name')
            ->orderByDesc('created_at')->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'nota' => $n->nota,
                'tipo' => $n->tipo,
                'admin' => $n->admin?->name,
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'usuario' => array_merge($this->row($usuario), [
                'nombre_gva' => $usuario->nombre_gva,
                'ccaa' => $usuario->ccaa?->name,
                'colectivo' => $usuario->colectivo?->name,
                'modo_activo' => $usuario->modo_activo,
                'onboarding_completed' => (bool) $usuario->onboarding_completed,
                'avatar_url' => $usuario->avatar_url,
                'especialidades' => $usuario->especialidades->map(fn ($e) => [
                    'specialty_name' => $e->specialty?->name,
                    'anyo' => $e->anyo,
                    'posicion_bolsa' => $e->posicion_bolsa,
                ]),
            ]),
            'suscripciones' => $suscripciones,
            'notas' => $notas,
        ]);
    }

    /**
     * Update basic editable fields (name, role).
     */
    public function update(Request $request, User $usuario): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', Rule::in(['user', 'admin', 'superadmin'])],
        ]);

        if (array_key_exists('name', $data)) {
            $usuario->name = $data['name'];
        }
        if (array_key_exists('role', $data)) {
            $usuario->forceFill(['role' => $data['role']]);
        }
        $usuario->save();

        return response()->json(['usuario' => $this->row($usuario->fresh())]);
    }

    /**
     * Manually change a user's plan (admin override).
     */
    public function cambiarPlan(Request $request, User $usuario): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(['free', 'interino', 'opositor', 'docente_pro', 'todo_en_uno'])],
            'plan_status' => ['sometimes', Rule::in(['active', 'trialing', 'past_due', 'canceled', 'none'])],
        ]);

        $status = $data['plan_status'] ?? ($data['plan'] === 'free' ? 'none' : 'active');

        $usuario->forceFill([
            'plan' => $data['plan'],
            'plan_status' => $status,
        ])->save();

        AdminNota::create([
            'user_id' => $usuario->id,
            'admin_id' => $request->user()->id,
            'nota' => "Plan cambiado manualmente a {$data['plan']} ({$status}).",
            'tipo' => 'manual',
        ]);

        return response()->json(['usuario' => $this->row($usuario->fresh())]);
    }

    /**
     * Attach an admin note to a user.
     */
    public function addNota(Request $request, User $usuario): JsonResponse
    {
        $data = $request->validate([
            'nota' => ['required', 'string', 'max:5000'],
        ]);

        $nota = AdminNota::create([
            'user_id' => $usuario->id,
            'admin_id' => $request->user()->id,
            'nota' => $data['nota'],
            'tipo' => 'manual',
        ]);

        return response()->json([
            'nota' => [
                'id' => $nota->id,
                'nota' => $nota->nota,
                'tipo' => $nota->tipo,
                'admin' => $request->user()->name,
                'created_at' => $nota->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Begin impersonating a user: issue a token for the target user and remember
     * (in the cache, TTL 2h) which admin is behind it.
     */
    public function impersonate(Request $request, User $usuario): JsonResponse
    {
        $admin = $request->user();

        if ($usuario->id === $admin->id) {
            return response()->json(['message' => 'No puedes suplantarte a ti mismo.'], 422);
        }

        // 2-hour DB-level expiry so the token auto-expires even if the cache
        // marker is evicted; the marker is only for audit/recognition.
        $expiresAt = now()->addHours(2);
        $newToken = $usuario->createToken('impersonation', ['*'], $expiresAt);
        $token = $newToken->plainTextToken;
        $tokenId = $newToken->accessToken->getKey();

        Cache::put('impersonation:'.$tokenId, [
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'user_id' => $usuario->id,
        ], $expiresAt);

        AdminNota::create([
            'user_id' => $usuario->id,
            'admin_id' => $admin->id,
            'nota' => "{$admin->name} inició una sesión de suplantación.",
            'tipo' => 'impersonacion',
        ]);

        return response()->json([
            'token' => $token,
            'usuario' => $this->row($usuario),
        ]);
    }

    /**
     * Stop an impersonation session: revoke the impersonation token and clear
     * its cache marker. Called with the impersonation token as bearer. The SPA
     * then restores the admin's own token (kept client-side).
     */
    public function stopImpersonate(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            Cache::forget('impersonation:'.$token->id);
            $token->delete();
        }

        return response()->json(['stopped' => true]);
    }

    /**
     * Suspend (or reactivate) a user. Suspending revokes all their tokens.
     */
    public function suspender(Request $request, User $usuario): JsonResponse
    {
        $data = $request->validate([
            'suspender' => ['required', 'boolean'],
            'motivo' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if ($usuario->isSuperAdmin()) {
            return response()->json(['message' => 'No se puede suspender a un super-admin.'], 422);
        }

        if ($data['suspender']) {
            $usuario->forceFill(['suspended_at' => now()])->save();
            $usuario->tokens()->delete();
            $nota = 'Cuenta suspendida.'.(! empty($data['motivo']) ? ' Motivo: '.$data['motivo'] : '');
        } else {
            $usuario->forceFill(['suspended_at' => null])->save();
            $nota = 'Cuenta reactivada.';
        }

        AdminNota::create([
            'user_id' => $usuario->id,
            'admin_id' => $request->user()->id,
            'nota' => $nota,
            'tipo' => 'sistema',
        ]);

        return response()->json(['usuario' => $this->row($usuario->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'plan' => $u->plan,
            'plan_label' => $u->planLabel(),
            'plan_status' => $u->plan_status,
            'is_paid' => $u->isPaid(),
            'suspended' => $u->isSuspended(),
            'last_active_at' => $u->last_active_at?->toIso8601String(),
            'created_at' => $u->created_at?->toIso8601String(),
        ];
    }
}
