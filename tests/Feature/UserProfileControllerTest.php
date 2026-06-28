<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Centro;
use App\Models\Colectivo;
use App\Models\Proceso;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserEspecialidad;
use App\Models\UserHistorial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private Ccaa $cv;

    private Colectivo $colectivo;

    private Specialty $specialty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cv = Ccaa::create(['code' => 'CV', 'name' => 'Comunitat Valenciana', 'is_active' => true]);
        $this->colectivo = Colectivo::create([
            'ccaa_id' => $this->cv->id,
            'code' => 'INTERINO',
            'name' => 'Interins',
            'body' => 'SECUNDARIA',
        ]);
        $this->specialty = Specialty::create([
            'code' => '218',
            'codigo' => '218',
            'name' => 'Orientación Educativa',
            'body' => 'Profesores de Enseñanza Secundaria',
            'education_level' => 'secundaria',
            'cuerpo' => 'SECUNDARIA',
            'ccaa_id' => $this->cv->id,
            'is_active' => true,
        ]);
    }

    public function test_me_and_profile_require_authentication(): void
    {
        $this->getJson('/api/v1/user/me')->assertUnauthorized();
        $this->getJson('/api/v1/user/profile')->assertUnauthorized();
    }

    public function test_public_routes_remain_open(): void
    {
        $this->getJson('/api/v1/specialties')->assertOk();
        $this->getJson('/api/v1/gva/noticias')->assertOk();
    }

    public function test_show_returns_full_profile(): void
    {
        $user = User::factory()->create([
            'ccaa_id' => $this->cv->id,
            'colectivo_id' => $this->colectivo->id,
            'nombre_gva' => 'PEREZ GOMEZ, ANA',
            'locale' => 'ca',
        ]);
        UserEspecialidad::create([
            'user_id' => $user->id,
            'specialty_id' => $this->specialty->id,
            'posicion_bolsa' => 42,
            'estado_bolsa' => 'Activat',
            'anyo' => 2026,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/profile')
            ->assertOk()
            ->assertJsonPath('nombre_gva', 'PEREZ GOMEZ, ANA')
            ->assertJsonPath('locale', 'ca')
            ->assertJsonPath('ccaa.code', 'CV')
            ->assertJsonPath('colectivo.code', 'INTERINO')
            ->assertJsonPath('especialidades.0.specialty_name', 'Orientación Educativa')
            ->assertJsonPath('especialidades.0.posicion_bolsa', 42);
    }

    public function test_update_changes_fields(): void
    {
        $user = User::factory()->create(['locale' => 'es']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user/profile', [
            'nombre_gva' => 'NOU NOM, TEST',
            'locale' => 'ca',
            'notificaciones_email' => false,
            'colectivo_id' => $this->colectivo->id,
            'ccaa_id' => $this->cv->id,
            'direccion_origen' => 'Carrer Major 1, València',
        ])->assertOk()
            ->assertJsonPath('nombre_gva', 'NOU NOM, TEST')
            ->assertJsonPath('locale', 'ca')
            ->assertJsonPath('notificaciones_email', false);

        $user->refresh();
        $this->assertSame('NOU NOM, TEST', $user->nombre_gva);
        $this->assertSame($this->colectivo->id, $user->colectivo_id);
        $this->assertSame('Carrer Major 1, València', $user->direccion_origen);
    }

    public function test_update_validates_locale(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user/profile', ['locale' => 'fr'])
            ->assertStatus(422);
    }

    public function test_store_especialidad_upserts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'specialty_id' => $this->specialty->id,
            'posicion_bolsa' => 10,
            'estado_bolsa' => 'Activat',
            'anyo' => 2026,
        ];

        $this->postJson('/api/v1/user/especialidades', $payload)
            ->assertCreated()
            ->assertJsonPath('posicion_bolsa', 10);

        // Same user+specialty+anyo updates rather than duplicates.
        $this->postJson('/api/v1/user/especialidades', array_merge($payload, ['posicion_bolsa' => 5]))
            ->assertCreated()
            ->assertJsonPath('posicion_bolsa', 5);

        $this->assertSame(1, UserEspecialidad::where('user_id', $user->id)->count());
    }

    public function test_destroy_especialidad_removes_it(): void
    {
        $user = User::factory()->create();
        UserEspecialidad::create([
            'user_id' => $user->id,
            'specialty_id' => $this->specialty->id,
            'posicion_bolsa' => 1,
            'estado_bolsa' => 'Activat',
            'anyo' => 2026,
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/user/especialidades/'.$this->specialty->id)
            ->assertOk()
            ->assertJsonPath('deleted', 1);

        $this->assertSame(0, UserEspecialidad::where('user_id', $user->id)->count());
    }

    public function test_dashboard_aggregates_data(): void
    {
        $user = User::factory()->create(['ccaa_id' => $this->cv->id]);

        $proceso = Proceso::create([
            'ccaa_id' => $this->cv->id,
            'colectivo_id' => $this->colectivo->id,
            'anyo' => 2026,
            'curso' => '2026-2027',
            'nombre' => 'Interins Secundària 2026-2027',
            'estado' => 'publicado',
            'fecha_fin_peticiones' => now()->addDays(10)->toDateString(),
            'fecha_adjudicacion' => now()->addDays(20)->toDateString(),
        ]);
        Proceso::create([
            'ccaa_id' => $this->cv->id,
            'colectivo_id' => $this->colectivo->id,
            'anyo' => 2025,
            'curso' => '2025-2026',
            'nombre' => 'Tancat 2025',
            'estado' => 'cerrado',
        ]);

        UserEspecialidad::create([
            'user_id' => $user->id,
            'specialty_id' => $this->specialty->id,
            'posicion_bolsa' => 3,
            'estado_bolsa' => 'Activat',
            'anyo' => 2026,
        ]);

        $centro = Centro::create([
            'ccaa_id' => $this->cv->id,
            'codigo' => '46011223',
            'nombre' => 'IES LA FONT',
            'tipo' => 'IES',
            'localidad' => 'València',
            'provincia' => 'València',
        ]);
        UserHistorial::create([
            'user_id' => $user->id,
            'specialty_id' => $this->specialty->id,
            'proceso_id' => $proceso->id,
            'anyo' => 2025,
            'posicion_definitiva' => 7,
            'estado' => 'Adjudicat',
            'centro_adjudicado_id' => $centro->id,
        ]);

        // A participant listing was imported for the active proceso → it is the
        // "último listado" surfaced to the dashboard, with its date.
        \App\Models\ParticipanteImportacion::create([
            'proceso_id' => $proceso->id, 'importado_en' => \Illuminate\Support\Carbon::parse('2026-06-22'),
            'total' => 5, 'nuevos' => 0, 'modificados' => 0, 'eliminados' => 0, 'es_primera' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/dashboard')
            ->assertOk()
            // Only the non-closed proceso is active.
            ->assertJsonCount(1, 'procesos_activos')
            ->assertJsonPath('procesos_activos.0.estado', 'publicado')
            ->assertJsonPath('procesos_activos.0.dias_para_adjudicacion', 20)
            ->assertJsonCount(1, 'mis_especialidades')
            ->assertJsonPath('mis_especialidades.0.posicion_bolsa', 3)
            ->assertJsonPath('resumen_historial.cursos_trabajados', 1)
            ->assertJsonPath('resumen_historial.ultimo_centro', 'IES LA FONT')
            ->assertJsonPath('resumen_historial.ultima_posicion', 7)
            ->assertJsonPath('proceso_listado.id', $proceso->id)
            ->assertJsonPath('proceso_listado.fecha', '2026-06-22');
    }
}
