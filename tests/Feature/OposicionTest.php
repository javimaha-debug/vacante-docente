<?php

namespace Tests\Feature;

use App\Models\Convocatoria;
use App\Models\DetectedDocument;
use App\Models\NormativaDocumento;
use App\Models\OposicionEspecialidad;
use App\Models\OposicionSesion;
use App\Models\OposicionTema;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OposicionTest extends TestCase
{
    use RefreshDatabase;

    public function test_especialidades_crud(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/oposicion/especialidades', [
            'especialidad_code' => '121',
            'cuerpo' => 'maestros',
        ])->assertCreated()->assertJsonPath('especialidad_code', '121')
            ->assertJsonPath('comunidad_autonoma', 'valenciana');

        $this->getJson('/api/v1/oposicion/especialidades')
            ->assertOk()->assertJsonCount(1, 'data');

        $id = OposicionEspecialidad::first()->id;
        $this->deleteJson("/api/v1/oposicion/especialidades/{$id}")
            ->assertOk()->assertJsonPath('deleted', true);

        $this->assertDatabaseCount('oposicion_especialidades', 0);
    }

    public function test_temas_create_update_filter_and_bulk(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/oposicion/temas', [
            'especialidad_code' => '121',
            'numero' => 1,
            'titulo' => 'Tema 1',
        ])->assertCreated()->assertJsonPath('status', 'pendiente');

        $tema = OposicionTema::first();

        // Moving status forward stamps last_studied_at.
        $this->patchJson("/api/v1/oposicion/temas/{$tema->id}", ['status' => 'dominado'])
            ->assertOk()->assertJsonPath('status', 'dominado');
        $this->assertNotNull($tema->fresh()->last_studied_at);

        // Bulk import continues numbering.
        $this->postJson('/api/v1/oposicion/temas/bulk', [
            'especialidad_code' => '121',
            'temas' => [['titulo' => 'Tema A'], ['titulo' => 'Tema B']],
        ])->assertCreated()->assertJsonPath('created', 2);

        $this->getJson('/api/v1/oposicion/temas?status=dominado')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/oposicion/temas?especialidad=121')
            ->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_cannot_touch_another_users_tema(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $tema = OposicionTema::create([
            'user_id' => $owner->id, 'especialidad_code' => '121',
            'numero' => 1, 'titulo' => 'X', 'status' => 'pendiente',
        ]);

        Sanctum::actingAs($other);
        $this->patchJson("/api/v1/oposicion/temas/{$tema->id}", ['status' => 'dominado'])
            ->assertForbidden();
    }

    public function test_sesiones_and_stats(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        OposicionTema::create(['user_id' => $user->id, 'especialidad_code' => '121', 'numero' => 1, 'titulo' => 'A', 'status' => 'dominado']);
        OposicionTema::create(['user_id' => $user->id, 'especialidad_code' => '121', 'numero' => 2, 'titulo' => 'B', 'status' => 'pendiente']);
        $temaId = OposicionTema::first()->id;

        // Two consecutive study days → streak of 2.
        OposicionSesion::create(['user_id' => $user->id, 'fecha' => now()->subDay()->toDateString(), 'minutos' => 30]);
        $this->postJson('/api/v1/oposicion/sesiones', [
            'minutos' => 60,
            'temas_estudiados' => [$temaId],
            'notas' => 'Buen día',
        ])->assertCreated();

        $this->getJson('/api/v1/oposicion/sesiones')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/oposicion/stats')->assertOk()
            ->assertJsonPath('total_minutos', 90)
            ->assertJsonPath('temas_by_status.dominado', 1)
            ->assertJsonPath('pct_dominado', 50)
            ->assertJsonPath('racha_dias', 2);
    }

    public function test_normativa_listing_and_filters(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        NormativaDocumento::create(['titulo' => 'LOE', 'categoria' => 'ley_organica', 'comunidad_autonoma' => 'nacional', 'vigente' => true]);
        NormativaDocumento::create(['titulo' => 'Decreto CV', 'categoria' => 'decreto', 'comunidad_autonoma' => 'valenciana', 'vigente' => false]);

        $this->getJson('/api/v1/normativa')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/normativa?categoria=decreto')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/normativa?vigente=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_convocatorias_listing_and_filters(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Convocatoria::create(['titulo' => 'Maestros', 'comunidad_autonoma' => 'valenciana', 'cuerpo' => 'maestros', 'estado' => 'en_proceso']);
        Convocatoria::create(['titulo' => 'Secundaria', 'comunidad_autonoma' => 'valenciana', 'cuerpo' => 'secundaria', 'estado' => 'rumor']);

        $this->getJson('/api/v1/convocatorias')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/convocatorias?estado=rumor')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/convocatorias?cuerpo=maestros')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_superadmin_can_manage_convocatorias_and_normativa(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/superadmin/convocatorias', [
            'titulo' => 'Nueva', 'comunidad_autonoma' => 'valenciana', 'estado' => 'anunciada',
        ])->assertCreated();

        $conv = Convocatoria::first();
        $this->patchJson("/api/v1/superadmin/convocatorias/{$conv->id}", ['estado' => 'convocada'])
            ->assertOk()->assertJsonPath('estado', 'convocada');

        $this->postJson('/api/v1/superadmin/normativa', [
            'titulo' => 'Doc', 'categoria' => 'orden', 'comunidad_autonoma' => 'valenciana',
        ])->assertCreated();

        $doc = NormativaDocumento::first();
        $this->assertEquals($admin->id, $doc->published_by);
        $this->patchJson("/api/v1/superadmin/normativa/{$doc->id}", ['vigente' => false])
            ->assertOk()->assertJsonPath('vigente', false);

        $this->deleteJson("/api/v1/superadmin/convocatorias/{$conv->id}")->assertOk();
    }

    public function test_convocatoria_links_to_a_detected_document(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($admin);

        $doc = DetectedDocument::create([
            'title' => 'Convocatoria oposiciones 2025 (PDF)',
            'document_type' => 'convocatoria',
            'status' => 'published',
            'source_url' => 'https://example.com/convocatoria.pdf',
        ]);

        $this->postJson('/api/v1/superadmin/convocatorias', [
            'titulo' => 'Vinculada', 'comunidad_autonoma' => 'valenciana',
            'estado' => 'convocada', 'source_document_id' => $doc->id,
        ])->assertCreated()
            ->assertJsonPath('source_document_id', $doc->id)
            ->assertJsonPath('source_document.titulo', $doc->title);

        // The public detail surfaces the linked document's title + url.
        $conv = Convocatoria::first();
        $this->getJson("/api/v1/convocatorias/{$conv->id}")
            ->assertOk()
            ->assertJsonPath('source_document.titulo', $doc->title)
            ->assertJsonPath('source_document.url', $doc->source_url);

        // A non-existent document id is rejected.
        $this->postJson('/api/v1/superadmin/convocatorias', [
            'titulo' => 'Mala', 'comunidad_autonoma' => 'valenciana',
            'estado' => 'rumor', 'source_document_id' => 99999,
        ])->assertStatus(422);
    }

    public function test_regular_user_cannot_access_superadmin_endpoints(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/superadmin/convocatorias', [
            'titulo' => 'X', 'comunidad_autonoma' => 'valenciana', 'estado' => 'rumor',
        ])->assertForbidden();
    }
}
