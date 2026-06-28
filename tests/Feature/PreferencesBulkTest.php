<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\Proceso;
use App\Models\Specialty;
use App\Models\UserList;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferencesBulkTest extends TestCase
{
    use RefreshDatabase;

    private function scaffold(): array
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'SUPRIMIDO', 'name' => 'Suprimits', 'body' => 'SECUNDARIA']);
        $spec = Specialty::create(['code' => '218', 'codigo' => '218', 'name' => 'Orientació', 'body' => 'PES', 'education_level' => 'secundaria', 'ccaa_id' => $cv->id]);
        $proceso = Proceso::create(['ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027', 'nombre' => 'P', 'estado' => 'publicado']);
        $vacancies = collect(range(1, 3))->map(fn ($i) => Vacancy::create([
            'specialty_id' => $spec->id, 'proceso_id' => $proceso->id, 'ccaa_id' => $cv->id,
            'num' => $i, 'provincia' => 'València', 'localidad' => 'VALÈNCIA',
            'centro_codigo' => '4600000'.$i, 'centro_nombre' => "IES $i", 'tipo_centro' => 'Secundaria', 'lloc' => '90000'.$i, 'year' => 2026,
        ]));

        $list = UserList::create(['session_token' => 'tok-pref', 'specialty_id' => $spec->id]);

        return compact('list', 'vacancies');
    }

    public function test_bulk_save_then_index_returns_ordered_preferences(): void
    {
        ['list' => $list, 'vacancies' => $vacancies] = $this->scaffold();
        [$a, $b, $c] = $vacancies->all();

        // Mix of statuses so the ordering (selected → neutral → discarded) is exercised.
        $this->withHeaders(['X-Session-Token' => 'tok-pref'])
            ->putJson("/api/v1/user-lists/{$list->id}/preferences/bulk", [
                'preferences' => [
                    ['vacancy_id' => $c->id, 'status' => 'discarded', 'position' => 0],
                    ['vacancy_id' => $a->id, 'status' => 'selected', 'position' => 1],
                    ['vacancy_id' => $b->id, 'status' => 'neutral', 'position' => 0],
                ],
            ])->assertOk()
            ->assertJsonCount(3, 'data')
            // selected first, then neutral, then discarded.
            ->assertJsonPath('data.0.vacancy_id', $a->id)
            ->assertJsonPath('data.0.status', 'selected')
            ->assertJsonPath('data.2.status', 'discarded');

        // The standalone index endpoint (initial kanban load) must not 500 either.
        $this->withHeaders(['X-Session-Token' => 'tok-pref'])
            ->getJson("/api/v1/user-lists/{$list->id}/preferences")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.status', 'selected');
    }

    public function test_adding_a_single_selection_works(): void
    {
        ['list' => $list, 'vacancies' => $vacancies] = $this->scaffold();
        $v = $vacancies->first();

        $this->withHeaders(['X-Session-Token' => 'tok-pref'])
            ->putJson("/api/v1/user-lists/{$list->id}/preferences/bulk", [
                'preferences' => [['vacancy_id' => $v->id, 'status' => 'selected', 'position' => 1]],
            ])->assertOk()
            ->assertJsonPath('data.0.vacancy_id', $v->id)
            ->assertJsonPath('data.0.status', 'selected');
    }

    public function test_another_session_cannot_touch_a_list(): void
    {
        ['list' => $list] = $this->scaffold();

        // Wrong token → forbidden.
        $this->withHeaders(['X-Session-Token' => 'otra-sesion'])
            ->getJson("/api/v1/user-lists/{$list->id}/preferences")
            ->assertForbidden();

        // No token → forbidden.
        $this->getJson("/api/v1/user-lists/{$list->id}/preferences")
            ->assertForbidden();
    }
}
