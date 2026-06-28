<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\DistanceCache;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserList;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Fase1bSidebarModosTest extends TestCase
{
    use RefreshDatabase;

    // --- Part 1: migration marks pre-Fase-0 users as onboarded ---

    public function test_migration_marks_pre_fase0_users_as_onboarding_completed(): void
    {
        $old = User::factory()->create(['created_at' => '2026-07-01 10:00:00']);
        $old->forceFill(['onboarding_completed' => false])->save();

        $recent = User::factory()->create(['created_at' => '2026-07-15 10:00:00']);
        $recent->forceFill(['onboarding_completed' => false])->save();

        $migration = require database_path('migrations/2026_07_10_000001_mark_existing_users_onboarding_completed.php');
        $migration->up();

        $this->assertTrue((bool) $old->fresh()->onboarding_completed, 'Pre-Fase-0 user should be onboarded.');
        $this->assertFalse((bool) $recent->fresh()->onboarding_completed, 'Post-Fase-0 user should be untouched.');
    }

    // --- Part 3: modo switching ---

    public function test_put_user_modo_updates_modo_activo(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user/modo', ['modo_activo' => 'docente'])
            ->assertOk()
            ->assertJsonPath('modo_activo', 'docente');

        $this->assertSame('docente', $user->fresh()->modo_activo);
    }

    public function test_put_user_modo_rejects_invalid_mode(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/user/modo', ['modo_activo' => 'invalido'])
            ->assertStatus(422);
    }

    // --- Part 5 Bug 1: search filters across the full dataset ---

    public function test_vacancies_search_filters_across_dataset(): void
    {
        ['spec' => $spec] = $this->scaffold();

        $this->getJson("/api/v1/vacancies?specialty_id={$spec->id}&year=2026")
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        // Searching narrows to the matching centre regardless of any grouping.
        $res = $this->getJson("/api/v1/vacancies?specialty_id={$spec->id}&year=2026&search=IES 2")
            ->assertOk()
            ->json();

        $this->assertSame(1, $res['meta']['total']);
        $this->assertSame('IES 2', $res['data'][0]['centro_nombre']);
    }

    // --- Part 5 Bug 2: distance data is present in the vacancy response ---

    public function test_vacancies_response_includes_distance_data_when_home_set(): void
    {
        ['spec' => $spec, 'vacancies' => $vacancies] = $this->scaffold();

        UserList::create([
            'session_token' => 'tok-dist', 'specialty_id' => $spec->id,
            'home_address' => 'València', 'home_lat' => 39.4699, 'home_lng' => -0.3763,
        ]);

        $first = $vacancies->first();
        DistanceCache::create([
            'vacancy_id' => $first->id, 'home_lat' => 39.4699, 'home_lng' => -0.3763,
            'mode' => 'driving_ida', 'duration_minutes' => 18, 'distance_km' => 12.4,
            'traffic_note' => null, 'calculated_at' => now(),
        ]);

        $res = $this->withHeaders(['X-Session-Token' => 'tok-dist'])
            ->getJson("/api/v1/vacancies?specialty_id={$spec->id}&year=2026&session_token=tok-dist")
            ->assertOk()
            ->json();

        $row = collect($res['data'])->firstWhere('id', $first->id);
        $this->assertNotNull($row['distances'], 'Vacancy should carry its distances.');
        $this->assertSame(18, $row['distances']['driving_ida']['duration_minutes']);
        $this->assertEqualsWithDelta(12.4, $row['distances']['driving_ida']['distance_km'], 0.01);
    }

    /**
     * @return array{cv:Ccaa, spec:Specialty, vacancies:\Illuminate\Support\Collection}
     */
    private function scaffold(): array
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'INTERINO', 'name' => 'Interins', 'body' => 'SECUNDARIA']);
        $spec = Specialty::create([
            'code' => '218', 'codigo' => '218', 'name' => 'Orientació', 'body' => 'PES',
            'education_level' => 'secundaria', 'ccaa_id' => $cv->id,
        ]);
        $vacancies = collect(range(1, 3))->map(fn ($i) => Vacancy::create([
            'specialty_id' => $spec->id, 'ccaa_id' => $cv->id, 'num' => $i,
            'provincia' => 'València', 'localidad' => 'VALÈNCIA', 'centro_codigo' => '4600000'.$i,
            'centro_nombre' => "IES $i", 'tipo_centro' => 'Secundaria', 'lloc' => '90000'.$i, 'year' => 2026,
        ]));

        return compact('cv', 'spec', 'vacancies');
    }
}
