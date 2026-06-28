<?php

namespace Tests\Feature;

use App\Jobs\MonitorGvaJob;
use App\Models\User;
use App\Services\GoogleMapsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressGeocodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_geocode_suggest_uses_places_autocomplete(): void
    {
        // Bind a configured maps service and fake the Places Autocomplete API.
        $this->app->instance(GoogleMapsService::class, new GoogleMapsService('test-key'));
        Http::fake([
            'maps.googleapis.com/maps/api/place/autocomplete/*' => Http::response([
                'status' => 'OK',
                'predictions' => [
                    ['description' => 'Carrer Major, 1, València, España', 'place_id' => 'p1'],
                    ['description' => 'Carrer Major, 2, Castelló, España', 'place_id' => 'p2'],
                ],
            ]),
        ]);

        $this->getJson('/api/v1/geocode?address=Carrer Major')
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.formatted_address', 'Carrer Major, 1, València, España')
            ->assertJsonPath('data.0.place_id', 'p1');
    }

    public function test_geocode_suggest_falls_back_to_geocoding_when_places_denied(): void
    {
        $this->app->instance(GoogleMapsService::class, new GoogleMapsService('test-key'));
        Http::fake([
            // Places API not enabled on the key.
            'maps.googleapis.com/maps/api/place/autocomplete/*' => Http::response(['status' => 'REQUEST_DENIED']),
            // Geocoding fallback still works.
            'maps.googleapis.com/maps/api/geocode/*' => Http::response([
                'status' => 'OK',
                'results' => [
                    ['formatted_address' => 'Carrer A, València', 'geometry' => ['location' => ['lat' => 39.4, 'lng' => -0.3]]],
                ],
            ]),
        ]);

        $this->getJson('/api/v1/geocode?address=Carrer')
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.formatted_address', 'Carrer A, València');
    }

    public function test_geocode_suggest_validates_input(): void
    {
        $this->getJson('/api/v1/geocode?address=ab')->assertStatus(422);
    }

    public function test_profile_update_uses_provided_coordinates_without_geocoding(): void
    {
        // No HTTP should be needed when coordinates are supplied.
        Http::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user/profile', [
            'direccion_origen' => 'Carrer Major 1, València',
            'lat_origen' => 39.123456,
            'lng_origen' => -0.456789,
        ])->assertOk();

        $user->refresh();
        $this->assertEquals(39.123456, (float) $user->lat_origen);
        $this->assertEquals(-0.456789, (float) $user->lng_origen);
        Http::assertNothingSent();
    }

    public function test_monitor_flags_participant_pdfs(): void
    {
        $job = new MonitorGvaJob();
        $this->assertTrue($job->isParticipantPdf('https://gva.es/lis_participants_2026.pdf'));
        $this->assertTrue($job->isParticipantPdf('https://gva.es/participantes.pdf'));
        $this->assertFalse($job->isParticipantPdf('https://gva.es/adjudicacions.pdf'));

        $html = '<a href="/lis_participants.pdf">Participants</a>';
        $rows = $job->parsePdfLinks($html, 'https://ceice.gva.es/x');
        $this->assertContains('lista_participantes', $rows[0]['keywords_matched']);
    }
}
