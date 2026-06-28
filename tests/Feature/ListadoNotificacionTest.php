<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Colectivo;
use App\Models\Proceso;
use App\Models\Specialty;
use App\Models\User;
use App\Models\UserEspecialidad;
use App\Models\Vacancy;
use App\Notifications\ListadoActualizado;
use App\Services\ListadoNotificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListadoNotificacionTest extends TestCase
{
    use RefreshDatabase;

    private function scaffold(): array
    {
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        $col = Colectivo::create(['ccaa_id' => $cv->id, 'code' => 'INTERINO', 'name' => 'Interins', 'body' => 'SECUNDARIA']);
        $spec = Specialty::create(['code' => '218', 'codigo' => '218', 'name' => 'Orientació', 'body' => 'PES', 'education_level' => 'secundaria', 'cuerpo' => 'SECUNDARIA', 'ccaa_id' => $cv->id]);
        $proceso = Proceso::create([
            'ccaa_id' => $cv->id, 'colectivo_id' => $col->id, 'anyo' => 2026, 'curso' => '2026-2027',
            'nombre' => 'Interins Secundària 2026-2027', 'estado' => 'publicado',
        ]);

        return compact('cv', 'col', 'spec', 'proceso');
    }

    public function test_notifies_users_following_changed_specialty(): void
    {
        Notification::fake();
        ['cv' => $cv, 'spec' => $spec, 'proceso' => $proceso] = $this->scaffold();

        $follower = User::factory()->create();
        UserEspecialidad::create(['user_id' => $follower->id, 'specialty_id' => $spec->id, 'anyo' => 2026]);

        $other = User::factory()->create(); // follows nothing → not notified

        Vacancy::create(['specialty_id' => $spec->id, 'proceso_id' => $proceso->id, 'ccaa_id' => $cv->id, 'num' => 1, 'provincia' => 'València', 'localidad' => 'VALÈNCIA', 'centro_codigo' => '46000001', 'centro_nombre' => 'IES A', 'tipo_centro' => 'Secundaria', 'lloc' => '900001', 'year' => 2026, 'cambio' => 'nueva']);

        $count = app(ListadoNotificacionService::class)->notifyVacantes($proceso, ['nuevas' => 1, 'modificadas' => 0, 'eliminadas' => 0]);

        $this->assertSame(1, $count);
        Notification::assertSentTo($follower, ListadoActualizado::class);
        Notification::assertNotSentTo($other, ListadoActualizado::class);
    }

    public function test_no_notification_when_no_changes(): void
    {
        Notification::fake();
        ['proceso' => $proceso] = $this->scaffold();

        $count = app(ListadoNotificacionService::class)->notifyVacantes($proceso, ['nuevas' => 0, 'modificadas' => 0, 'eliminadas' => 0]);

        $this->assertSame(0, $count);
        Notification::assertNothingSent();
    }

    public function test_notifies_users_removed_from_listing(): void
    {
        Notification::fake();
        ['proceso' => $proceso] = $this->scaffold();

        $removed = User::factory()->create(['nombre_gva' => 'PEREZ GOMEZ, ANA']);
        $other = User::factory()->create(['nombre_gva' => 'OTRA PERSONA, X']);

        $count = app(ListadoNotificacionService::class)
            ->notifyEliminados($proceso, ['perez gomez, ana']);

        $this->assertSame(1, $count);
        Notification::assertSentTo($removed, \App\Notifications\EliminadoDeListado::class);
        Notification::assertNotSentTo($other, \App\Notifications\EliminadoDeListado::class);
    }

    public function test_inbox_lists_and_marks_read(): void
    {
        ['spec' => $spec, 'proceso' => $proceso] = $this->scaffold();
        $user = User::factory()->create();

        $user->notify(new ListadoActualizado($proceso, 'vacantes', ['nuevas' => 2, 'modificadas' => 1, 'eliminadas' => 0]));

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notificaciones')
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('data.0.data.tipo', 'vacantes')
            ->assertJsonPath('data.0.data.titulo', "Listado de vacantes actualizado — {$proceso->nombre}");

        $this->postJson('/api/v1/notificaciones/leer')
            ->assertOk()
            ->assertJsonPath('unread', 0);
    }

    public function test_email_channel_respects_user_preference(): void
    {
        ['proceso' => $proceso] = $this->scaffold();

        $optedIn = User::factory()->create(['notificaciones_email' => true]);
        $optedOut = User::factory()->create(['notificaciones_email' => false]);

        $note = new ListadoActualizado($proceso, 'participantes', ['nuevos' => 1, 'modificados' => 0, 'eliminados' => 0]);

        $this->assertContains('mail', $note->via($optedIn));
        $this->assertNotContains('mail', $note->via($optedOut));
        $this->assertContains('database', $note->via($optedOut));
    }
}
