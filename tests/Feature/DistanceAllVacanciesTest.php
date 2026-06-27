<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\DistanceCache;
use App\Models\Proceso;
use App\Models\Specialty;
use App\Models\UserList;
use App\Models\Vacancy;
use App\Services\GoogleMapsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DistanceAllVacanciesTest extends TestCase
{
    use RefreshDatabase;

    private function scaffold(): array
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'INTERINO', 'name' => 'Interins', 'body' => 'SECUNDARIA']);
        $spec = Specialty::create(['code' => '218', 'codigo' => '218', 'name' => 'Orientació', 'body' => 'PES', 'education_level' => 'secundaria', 'ccaa_id' => $cv->id]);
        $proceso = Proceso::create(['ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027', 'nombre' => 'P', 'estado' => 'publicado']);
        $vacancies = collect(range(1, 3))->map(fn ($i) => Vacancy::create([
            'specialty_id' => $spec->id, 'proceso_id' => $proceso->id, 'ccaa_id' => $cv->id,
            'num' => $i, 'provincia' => 'València', 'localidad' => 'VALÈNCIA',
            'centro_codigo' => '4600000'.$i, 'centro_nombre' => "IES $i", 'tipo_centro' => 'Secundaria', 'lloc' => '90000'.$i, 'year' => 2026,
        ]));

        return compact('cv', 'spec', 'proceso', 'vacancies');
    }

    public function test_calculates_distances_for_explicit_vacancy_ids_without_selecting(): void
    {
        $this->app->instance(GoogleMapsService::class, new GoogleMapsService('test-key'));
        Http::fake([
            'maps.googleapis.com/maps/api/distancematrix/*' => Http::response([
                'status' => 'OK',
                'rows' => [[
                    'elements' => array_fill(0, 3, [
                        'status' => 'OK',
                        'duration' => ['value' => 600],
                        'duration_in_traffic' => ['value' => 660],
                        'distance' => ['value' => 12000],
                    ]),
                ]],
            ]),
        ]);

        ['spec' => $spec, 'vacancies' => $vacancies] = $this->scaffold();

        $list = UserList::create([
            'session_token' => 'tok-abc', 'specialty_id' => $spec->id,
            'home_address' => 'València', 'home_lat' => 39.4699, 'home_lng' => -0.3763,
        ]);

        $ids = $vacancies->pluck('id')->all();

        // No vacancy is "selected" — we pass the full list explicitly.
        $this->postJson("/api/v1/user-lists/{$list->id}/calculate-distances", [
            'mode' => 'driving',
            'vacancy_ids' => $ids,
        ])->assertOk()
            ->assertJsonPath('count', 3)
            // Everything fit under the per-request cap → nothing left to do.
            ->assertJsonPath('remaining', 0);

        // All three were cached.
        $this->assertSame(3, DistanceCache::where('mode', 'driving')->count());
        $this->assertEqualsWithDelta(12.0, (float) DistanceCache::first()->distance_km, 0.01);
    }

    public function test_proceso_vacantes_attaches_cached_distances(): void
    {
        ['spec' => $spec, 'proceso' => $proceso, 'vacancies' => $vacancies] = $this->scaffold();

        UserList::create([
            'session_token' => 'tok-xyz', 'specialty_id' => $spec->id,
            'home_address' => 'València', 'home_lat' => 39.4699, 'home_lng' => -0.3763,
        ]);

        foreach ($vacancies as $v) {
            DistanceCache::create([
                'vacancy_id' => $v->id, 'home_lat' => 39.4699, 'home_lng' => -0.3763,
                'mode' => 'driving', 'duration_minutes' => 18, 'distance_km' => 12.5, 'calculated_at' => now(),
            ]);
        }

        $this->getJson("/api/v1/procesos/{$proceso->id}/vacantes?especialidad={$spec->id}&session_token=tok-xyz")
            ->assertOk()
            ->assertJsonPath('data.0.distances.driving.distance_km', 12.5)
            ->assertJsonPath('data.0.distances.driving.duration_minutes', 18);
    }
}
