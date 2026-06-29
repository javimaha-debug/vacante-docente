<?php

namespace Tests\Feature;

use App\Jobs\GenerateTemarioEnrichmentJob;
use App\Models\AiConversation;
use App\Models\OposicionTema;
use App\Models\TemaOficial;
use App\Models\TemarioOficial;
use App\Models\User;
use App\Services\AiAssistantService;
use App\Services\AnthropicService;
use App\Services\TemarioBoeParser;
use App\Services\TemarioSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TemarioOficialTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_extracts_especialidades_and_temas(): void
    {
        $text = <<<'TXT'
        Especialidad: Orientación Educativa
        Tema 1. La psicología del desarrollo y sus implicaciones educativas.
        Tema 2. Modelos de orientación e intervención psicopedagógica.
        Especialidad: Lengua Castellana y Literatura
        1. La comunicación. Elementos y funciones.
        2. El lenguaje como sistema.
        3. Las variedades de la lengua.
        TXT;

        $parsed = (new TemarioBoeParser)->parse($text);

        $this->assertCount(2, $parsed);
        $this->assertSame('Orientación Educativa', $parsed[0]['especialidad_nombre']);
        $this->assertCount(2, $parsed[0]['temas']);
        $this->assertSame(1, $parsed[0]['temas'][0]['numero']);
        $this->assertStringContainsString('psicología del desarrollo', $parsed[0]['temas'][0]['titulo']);
        $this->assertCount(3, $parsed[1]['temas']);
    }

    public function test_sync_service_persists_and_dispatches_enrichment(): void
    {
        Bus::fake();

        $parsed = [[
            'especialidad_nombre' => 'Orientación Educativa',
            'especialidad_code' => '008',
            'temas' => [
                ['numero' => 1, 'titulo' => 'Tema uno'],
                ['numero' => 2, 'titulo' => 'Tema dos'],
            ],
        ]];

        $result = (new TemarioSyncService)->ingestParsed('secundaria', $parsed, [
            'source_url' => 'https://boe.es/x.pdf', 'source_order' => 'EDU/3138/2011', 'published_at' => '2011-11-18',
        ]);

        $this->assertSame(1, $result['temarios']);
        $this->assertSame(2, $result['temas']);
        $this->assertDatabaseHas('temarios_oficiales', ['especialidad_code' => '008', 'cuerpo' => 'secundaria', 'total_temas' => 2]);
        $this->assertSame(2, TemaOficial::count());

        Bus::assertDispatched(GenerateTemarioEnrichmentJob::class);

        // Idempotent re-sync keeps a single temario.
        (new TemarioSyncService)->ingestParsed('secundaria', $parsed, [], false);
        $this->assertSame(1, TemarioOficial::count());
    }

    public function test_enrichment_job_fills_esquema_and_bibliografia(): void
    {
        $temario = TemarioOficial::create([
            'cuerpo' => 'secundaria', 'especialidad_code' => '008', 'especialidad_nombre' => 'Orientación Educativa',
            'total_temas' => 1,
        ]);
        $tema = TemaOficial::create(['temario_id' => $temario->id, 'numero' => 1, 'titulo' => 'La orientación educativa']);

        $esquemaBody = [
            'content' => [['type' => 'text', 'text' => json_encode([
                'esquema' => [['punto' => 'I. Introducción', 'subpuntos' => ['1.1 Concepto']]],
                'keywords' => ['orientación', 'psicopedagogía'],
                'tiempo_estimado_minutos' => 120,
            ])]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ];
        $bibBody = [
            'content' => [['type' => 'text', 'text' => json_encode([
                ['tipo' => 'libro', 'titulo' => 'Orientación educativa', 'autor' => 'X', 'año' => 2020],
            ])]],
            'usage' => ['input_tokens' => 80, 'output_tokens' => 40],
        ];

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()->push($esquemaBody, 200)->push($bibBody, 200),
        ]);

        (new GenerateTemarioEnrichmentJob($temario->id))->handle(app(AnthropicService::class));

        $tema->refresh();
        $this->assertNotNull($tema->generated_at);
        $this->assertSame('I. Introducción', $tema->esquema[0]['punto']);
        $this->assertContains('orientación', $tema->keywords);
        $this->assertSame(120, $tema->tiempo_estimado_minutos);
        $this->assertSame('Orientación educativa', $tema->bibliografia[0]['titulo']);
    }

    public function test_user_can_check_and_import_official_temario(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $temario = TemarioOficial::create([
            'cuerpo' => 'secundaria', 'especialidad_code' => '218', 'especialidad_nombre' => 'Orientación Educativa', 'total_temas' => 2,
        ]);
        $oficial1 = TemaOficial::create(['temario_id' => $temario->id, 'numero' => 1, 'titulo' => 'Tema 1', 'esquema' => [['punto' => 'I']], 'generated_at' => now()]);
        TemaOficial::create(['temario_id' => $temario->id, 'numero' => 2, 'titulo' => 'Tema 2']);

        $this->getJson('/api/v1/oposicion/temario-oficial?especialidad_code=218')
            ->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath('total_temas', 2)
            ->assertJsonCount(2, 'preview');

        $this->postJson('/api/v1/oposicion/temas/import-oficial', ['especialidad_code' => '218'])
            ->assertCreated()->assertJsonPath('imported', 2);

        $this->assertDatabaseHas('oposicion_temas', [
            'user_id' => $user->id, 'especialidad_code' => '218', 'numero' => 1, 'es_oficial' => true, 'tema_oficial_id' => $oficial1->id,
        ]);

        // The esquema is reachable through the user's tema.
        $userTema = OposicionTema::where('user_id', $user->id)->where('numero', 1)->first();
        $this->getJson("/api/v1/oposicion/temas/{$userTema->id}/oficial")
            ->assertOk()->assertJsonPath('esquema.0.punto', 'I');

        // Re-import does not duplicate.
        $this->postJson('/api/v1/oposicion/temas/import-oficial', ['especialidad_code' => '218'])
            ->assertCreated()->assertJsonPath('imported', 0);
    }

    public function test_assistant_system_prompt_includes_official_tema(): void
    {
        $user = User::factory()->create();
        $temario = TemarioOficial::create(['cuerpo' => 'secundaria', 'especialidad_code' => '218', 'especialidad_nombre' => 'Orientación', 'total_temas' => 1]);
        TemaOficial::create([
            'temario_id' => $temario->id, 'numero' => 3, 'titulo' => 'La acción tutorial',
            'esquema' => [['punto' => 'I. Marco', 'subpuntos' => ['1.1 LOMLOE']]], 'keywords' => ['tutoría', 'PAT'], 'generated_at' => now(),
        ]);

        $conversation = AiConversation::create([
            'user_id' => $user->id, 'mode' => 'chat', 'context_type' => 'temario',
            'especialidad_code' => '218', 'tema_numero' => 3,
        ]);

        $prompt = app(AiAssistantService::class)->systemPrompt($user, $conversation);

        $this->assertStringContainsString('TEMA OFICIAL EN ESTUDIO', $prompt);
        $this->assertStringContainsString('La acción tutorial', $prompt);
        $this->assertStringContainsString('tutoría', $prompt);
    }

    public function test_superadmin_temarios_endpoints(): void
    {
        Bus::fake();
        $admin = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($admin);

        $temario = TemarioOficial::create(['cuerpo' => 'maestros', 'especialidad_code' => '121', 'especialidad_nombre' => 'Educación Primaria', 'total_temas' => 2]);
        TemaOficial::create(['temario_id' => $temario->id, 'numero' => 1, 'titulo' => 'A', 'generated_at' => now(), 'esquema' => [['punto' => 'I']]]);
        TemaOficial::create(['temario_id' => $temario->id, 'numero' => 2, 'titulo' => 'B']);

        $this->getJson('/api/v1/superadmin/temarios')->assertOk()
            ->assertJsonPath('data.0.total_temas', 2)
            ->assertJsonPath('data.0.pct_enriquecido', 50);

        $this->getJson('/api/v1/superadmin/temarios/stats')->assertOk()
            ->assertJsonPath('total_temas', 2)
            ->assertJsonPath('pct_esquema', 50);

        $this->getJson("/api/v1/superadmin/temarios/{$temario->id}/temas")->assertOk()->assertJsonCount(2, 'data');

        $tema = TemaOficial::where('numero', 2)->first();
        $this->patchJson("/api/v1/superadmin/temas-oficiales/{$tema->id}", ['titulo' => 'B mejorado'])
            ->assertOk()->assertJsonPath('titulo', 'B mejorado');

        $this->postJson("/api/v1/superadmin/temarios/{$temario->id}/regenerate")->assertOk()->assertJsonPath('queued', true);
        Bus::assertDispatched(GenerateTemarioEnrichmentJob::class);

        // Non-admins are blocked.
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/superadmin/temarios')->assertForbidden();
    }
}
