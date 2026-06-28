<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => self::SECRET]);
    }

    /**
     * Build a request with a valid Stripe-Signature header for the payload.
     */
    private function signedPost(array $event): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode($event);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", self::SECRET);

        return $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}"],
            $payload,
        );
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't=1,v1=bogus'],
            json_encode(['type' => 'customer.subscription.created']),
        )->assertStatus(400);
    }

    public function test_subscription_created_upgrades_user(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['stripe_customer_id' => 'cus_123'])->save();

        $this->signedPost([
            'type' => 'customer.subscription.created',
            'data' => ['object' => [
                'id' => 'sub_abc',
                'customer' => 'cus_123',
                'status' => 'active',
                'current_period_start' => now()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
                'cancel_at_period_end' => false,
                'items' => ['data' => [['price' => ['id' => 'price_interino']]]],
            ]],
        ])->assertOk()->assertJsonPath('received', true);

        // No plan maps to price_interino (prices unset in Fase 0), so the plan
        // falls back to the user's current plan but the status syncs to active.
        $fresh = $user->fresh();
        $this->assertSame('active', $fresh->plan_status);
        $this->assertSame('sub_abc', $fresh->stripe_subscription_id);
        $this->assertDatabaseHas('suscripciones', [
            'user_id' => $user->id,
            'stripe_subscription_id' => 'sub_abc',
            'status' => 'active',
        ]);
    }

    public function test_subscription_deleted_downgrades_to_free(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'stripe_customer_id' => 'cus_456',
            'stripe_subscription_id' => 'sub_del',
            'plan' => 'interino',
            'plan_status' => 'active',
        ])->save();

        $this->signedPost([
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => [
                'id' => 'sub_del',
                'customer' => 'cus_456',
                'status' => 'canceled',
            ]],
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertSame('free', $fresh->plan);
        $this->assertSame('canceled', $fresh->plan_status);
    }

    public function test_invoice_payment_failed_marks_past_due(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'stripe_customer_id' => 'cus_789',
            'plan' => 'opositor',
            'plan_status' => 'active',
        ])->save();

        $this->signedPost([
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['customer' => 'cus_789']],
        ])->assertOk();

        $this->assertSame('past_due', $user->fresh()->plan_status);
    }
}
