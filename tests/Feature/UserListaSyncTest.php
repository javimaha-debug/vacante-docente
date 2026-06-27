<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\Proceso;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserList;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserListaSyncTest extends TestCase
{
    use RefreshDatabase;

    private function scaffold(): array
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'Comunitat Valenciana', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'INTERINO', 'name' => 'Interins', 'body' => 'SECUNDARIA']);
        $spec = Specialty::create([
            'code' => '218', 'codigo' => '218', 'name' => 'Orientación Educativa',
            'body' => 'PES', 'education_level' => 'secundaria', 'cuerpo' => 'SECUNDARIA', 'ccaa_id' => $cv->id,
        ]);
        $proceso = Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027',
            'nombre' => 'Interins Secundària 2026-2027', 'estado' => 'publicado',
        ]);
        $vacancies = collect(range(1, 3))->map(fn ($i) => Vacancy::create([
            'specialty_id' => $spec->id, 'proceso_id' => $proceso->id, 'ccaa_id' => $cv->id,
            'num' => $i, 'provincia' => 'València', 'localidad' => 'VALÈNCIA',
            'centro_codigo' => '4600000'.$i, 'centro_nombre' => "IES $i", 'tipo_centro' => 'Secundaria',
            'lloc' => '90000'.$i, 'year' => 2026,
        ]));

        return compact('cv', 'spec', 'proceso', 'vacancies');
    }

    public function test_lista_requires_auth(): void
    {
        $this->getJson('/api/v1/user/lista')->assertUnauthorized();
        $this->putJson('/api/v1/user/lista/sync')->assertUnauthorized();
    }

    public function test_sync_then_get_returns_ordered_list(): void
    {
        ['spec' => $spec, 'proceso' => $proceso, 'vacancies' => $vacancies] = $this->scaffold();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $items = $vacancies->values()->map(fn ($v, $i) => [
            'vacancy_id' => $v->id, 'position' => $i + 1, 'status' => 'selected', 'notes' => "nota $i",
        ])->all();

        $this->putJson('/api/v1/user/lista/sync', [
            'specialty_id' => $spec->id, 'proceso_id' => $proceso->id, 'items' => $items,
        ])->assertOk()->assertJsonPath('saved', 3);

        // A user-scoped list was created (not session-based).
        $this->assertDatabaseHas('user_lists', ['user_id' => $user->id, 'proceso_id' => $proceso->id]);

        $this->getJson('/api/v1/user/lista?specialty_id='.$spec->id.'&proceso_id='.$proceso->id)
            ->assertOk()
            ->assertJsonCount(3, 'items')
            ->assertJsonPath('items.0.notes', 'nota 0')
            ->assertJsonPath('items.0.position', 1);
    }

    public function test_sync_replaces_previous_items(): void
    {
        ['spec' => $spec, 'proceso' => $proceso, 'vacancies' => $vacancies] = $this->scaffold();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = fn (array $vs) => [
            'specialty_id' => $spec->id, 'proceso_id' => $proceso->id,
            'items' => collect($vs)->map(fn ($v, $i) => ['vacancy_id' => $v->id, 'position' => $i + 1, 'status' => 'selected'])->all(),
        ];

        $this->putJson('/api/v1/user/lista/sync', $payload($vacancies->all()))->assertOk();
        $this->putJson('/api/v1/user/lista/sync', $payload([$vacancies->first()]))->assertOk()->assertJsonPath('saved', 1);

        $list = UserList::where('user_id', $user->id)->first();
        $this->assertSame(1, $list->preferences()->count());
    }
}
