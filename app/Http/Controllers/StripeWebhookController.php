<?php

namespace App\Http\Controllers;

use App\Models\AdminNota;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    /** Map Stripe subscription statuses to our plan_status enum. */
    private const STATUS_MAP = [
        'active' => 'active',
        'trialing' => 'trialing',
        'past_due' => 'past_due',
        'unpaid' => 'past_due',
        'canceled' => 'canceled',
        'incomplete' => 'none',
        'incomplete_expired' => 'canceled',
        'paused' => 'none',
    ];

    /**
     * Receive and dispatch Stripe webhook events. The request signature is
     * verified against STRIPE_WEBHOOK_SECRET before anything is processed.
     */
    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $secret) {
            Log::warning('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not configured.');

            return response()->json(['error' => 'webhook not configured'], 500);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, (string) $signature, $secret);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'invalid signature'], 400);
        }

        $object = $event->data->object;

        match ($event->type) {
            'customer.subscription.created',
            'customer.subscription.updated' => $this->onSubscriptionUpserted($object),
            'customer.subscription.deleted' => $this->onSubscriptionDeleted($object),
            'invoice.payment_succeeded' => $this->onInvoicePaymentSucceeded($object),
            'invoice.payment_failed' => $this->onInvoicePaymentFailed($object),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    /**
     * A subscription was created or updated: sync the suscripciones row and the
     * user's plan/plan_status.
     */
    private function onSubscriptionUpserted(object $sub): void
    {
        $user = $this->resolveUser($sub->customer ?? null);
        if (! $user) {
            return;
        }

        $priceId = $this->priceId($sub);
        $planCodigo = $this->planForPrice($priceId) ?? $user->plan ?? 'free';
        $status = self::STATUS_MAP[$sub->status ?? ''] ?? 'none';
        $periodEnd = isset($sub->current_period_end) ? Carbon::createFromTimestamp($sub->current_period_end) : null;

        Suscripcion::updateOrCreate(
            ['stripe_subscription_id' => $sub->id],
            [
                'user_id' => $user->id,
                'plan_codigo' => $planCodigo,
                'stripe_customer_id' => $sub->customer ?? null,
                'status' => $sub->status ?? 'unknown',
                'current_period_start' => isset($sub->current_period_start)
                    ? Carbon::createFromTimestamp($sub->current_period_start) : null,
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => (bool) ($sub->cancel_at_period_end ?? false),
                'canceled_at' => isset($sub->canceled_at) && $sub->canceled_at
                    ? Carbon::createFromTimestamp($sub->canceled_at) : null,
                'trial_start' => isset($sub->trial_start) && $sub->trial_start
                    ? Carbon::createFromTimestamp($sub->trial_start) : null,
                'trial_end' => isset($sub->trial_end) && $sub->trial_end
                    ? Carbon::createFromTimestamp($sub->trial_end) : null,
            ],
        );

        $user->forceFill([
            'plan' => in_array($status, ['active', 'trialing'], true) ? $planCodigo : $user->plan,
            'plan_status' => $status,
            'plan_expires_at' => $periodEnd,
            'stripe_customer_id' => $sub->customer ?? $user->stripe_customer_id,
            'stripe_subscription_id' => $sub->id,
        ])->save();

        $this->logNota($user, "Suscripción {$sub->status} sincronizada desde Stripe (plan: {$planCodigo}).");
    }

    /**
     * A subscription was deleted: cancel locally and drop the user to free.
     */
    private function onSubscriptionDeleted(object $sub): void
    {
        $suscripcion = Suscripcion::where('stripe_subscription_id', $sub->id)->first();
        $user = $suscripcion?->user ?? $this->resolveUser($sub->customer ?? null);

        if ($suscripcion) {
            $suscripcion->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'cancel_at_period_end' => false,
            ]);
        }

        if ($user) {
            $user->forceFill([
                'plan' => 'free',
                'plan_status' => 'canceled',
                'plan_expires_at' => null,
                'stripe_subscription_id' => null,
            ])->save();

            $this->logNota($user, 'Suscripción cancelada (customer.subscription.deleted).');
        }
    }

    /**
     * An invoice was paid: keep the plan active and push the expiry forward.
     */
    private function onInvoicePaymentSucceeded(object $invoice): void
    {
        $user = $this->resolveUser($invoice->customer ?? null);
        if (! $user) {
            return;
        }

        $periodEnd = $this->invoicePeriodEnd($invoice);

        $user->forceFill([
            'plan_status' => 'active',
            'plan_expires_at' => $periodEnd ?? $user->plan_expires_at,
        ])->save();

        $this->logNota($user, 'Pago de factura confirmado (invoice.payment_succeeded).');
    }

    /**
     * An invoice payment failed: flag the account as past_due.
     */
    private function onInvoicePaymentFailed(object $invoice): void
    {
        $user = $this->resolveUser($invoice->customer ?? null);
        if (! $user) {
            return;
        }

        $user->forceFill(['plan_status' => 'past_due'])->save();

        $this->logNota($user, 'Pago de factura fallido (invoice.payment_failed).');
    }

    private function resolveUser(?string $customerId): ?User
    {
        if (! $customerId) {
            return null;
        }

        return User::where('stripe_customer_id', $customerId)->first();
    }

    private function priceId(object $sub): ?string
    {
        $items = $sub->items->data ?? [];

        return $items[0]->price->id ?? null;
    }

    private function planForPrice(?string $priceId): ?string
    {
        if (! $priceId) {
            return null;
        }

        return Plan::where('stripe_price_id_mensual', $priceId)
            ->orWhere('stripe_price_id_anual', $priceId)
            ->value('codigo');
    }

    private function invoicePeriodEnd(object $invoice): ?Carbon
    {
        $line = $invoice->lines->data[0] ?? null;
        $end = $line->period->end ?? null;

        return $end ? Carbon::createFromTimestamp($end) : null;
    }

    private function logNota(User $user, string $nota): void
    {
        AdminNota::create([
            'user_id' => $user->id,
            'admin_id' => $user->id,
            'nota' => $nota,
            'tipo' => 'stripe',
        ]);
    }
}
