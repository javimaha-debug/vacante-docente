<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TablonResponderRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_reply_link_serves_the_spa(): void
    {
        $url = URL::temporarySignedRoute('tablon.responder', now()->addDays(7), ['contacto' => 123]);

        // A valid signature serves the SPA shell (React renders the reply form).
        $this->get($url)->assertOk()->assertSee('id="app"', false);
    }

    public function test_unsigned_or_tampered_link_is_rejected(): void
    {
        // No signature → 403 from the `signed` middleware.
        $this->get('/tablon/responder/123')->assertForbidden();

        // Tampered signature → 403.
        $this->get('/tablon/responder/123?expires=9999999999&signature=deadbeef')->assertForbidden();
    }
}
