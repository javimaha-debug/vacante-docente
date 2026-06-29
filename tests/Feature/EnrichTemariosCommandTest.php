<?php

namespace Tests\Feature;

use App\Models\TemaOficial;
use App\Models\TemarioOficial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnrichTemariosCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAnthropicPair(): void
    {
        $esquema = [
            'content' => [['type' => 'text', 'text' => json_encode([
                'esquema' => [['punto' => 'I. Concepto', 'subpuntos' => ['1.1 Definición']]],
                'keywords' => ['orientación', 'tutoría'],
                'tiempo_estimado_minutos' => 90,
            ])]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ];
        $bib = [
            'content' => [['type' => 'text', 'text' => json_encode([
                ['tipo' => 'libro', 'titulo' => 'La orientación educativa', 'autor' => 'García', 'año' => 2019],
            ])]],
            'usage' => ['input_tokens' => 80, 'output_tokens' => 40],
        ];
        Http::fake(['api.anthropic.com/*' => Http::sequence()->push($esquema, 200)->push($bib, 200)]);
    }

    private function makeTemario(string $nombre = 'Orientación Educativa', int $temas = 2): TemarioOficial
    {
        $temario = TemarioOficial::create([
            'cuerpo' => 'secundaria',
            'especialidad_code' => '218',
            'especialidad_nombre' => $nombre,
            'comunidad_autonoma' => 'nacional',
            'total_temas' => $temas,
        ]);
        for ($i = 1; $i <= $temas; $i++) {
            TemaOficial::create(['temario_id' => $temario->id, 'numero' => $i, 'titulo' => "Tema {$i}"]);
        }

        return $temario;
    }

    public function test_enrich_command_aborts_when_temas_exceed_limit_without_confirm(): void
    {
        $this->makeTemario('Orientación Educativa', 5);

        $this->artisan('temarios:enrich', [
            '--especialidad' => 'Orientación',
            '--limit' => 3,
            '--sync' => true,
        ])->assertFailed();

        $this->assertSame(0, TemaOficial::whereNotNull('generated_at')->count());
    }

    public function test_enrich_command_proceeds_with_confirm_flag(): void
    {
        $this->makeTemario('Orientación Educativa', 1);

        $response = [
            'content' => [['type' => 'text', 'text' => json_encode([
                'esquema' => [['punto' => 'I.', 'subpuntos' => []]],
                'keywords' => ['orientación'],
                'tiempo_estimado_minutos' => 60,
            ])]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ];
        $bib = [
            'content' => [['type' => 'text', 'text' => json_encode([
                ['tipo' => 'libro', 'titulo' => 'Ref', 'autor' => 'A', 'año' => 2020],
            ])]],
            'usage' => ['input_tokens' => 80, 'output_tokens' => 40],
        ];
        Http::fake(['api.anthropic.com/*' => Http::sequence()->push($response, 200)->push($bib, 200)]);

        $this->artisan('temarios:enrich', [
            '--especialidad' => 'Orientación',
            '--limit' => 0,
            '--confirm' => true,
            '--sync' => true,
        ])->assertSuccessful();

        $this->assertSame(1, TemaOficial::whereNotNull('generated_at')->count());
    }

    public function test_enrich_command_warns_when_no_temarios_found(): void
    {
        $this->artisan('temarios:enrich', ['--especialidad' => 'NoExiste', '--sync' => true])
            ->assertFailed();
    }

    public function test_enrich_command_skips_already_enriched_temas(): void
    {
        $temario = $this->makeTemario('Orientación Educativa', 2);
        // Mark tema 1 as already enriched.
        TemaOficial::where('temario_id', $temario->id)->where('numero', 1)->update(['generated_at' => now()]);

        // Only 1 tema pending; 2 Anthropic calls needed (esquema + bib for that one tema).
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'esquema' => [['punto' => 'I.', 'subpuntos' => []]],
                    'keywords' => ['k'],
                    'tiempo_estimado_minutos' => 60,
                ])]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ], 200)
            ->push([
                'content' => [['type' => 'text', 'text' => json_encode([
                    ['tipo' => 'libro', 'titulo' => 'Ref', 'autor' => 'A', 'año' => 2020],
                ])]],
                'usage' => ['input_tokens' => 80, 'output_tokens' => 40],
            ], 200),
        ]);

        $this->artisan('temarios:enrich', [
            '--especialidad' => 'Orientación',
            '--confirm' => true,
            '--sync' => true,
        ])->assertSuccessful();

        // Only 1 tema should now be enriched (the other was already done and untouched).
        $this->assertSame(2, TemaOficial::whereNotNull('generated_at')->count());
    }

    public function test_sync_boe_does_not_enrich_by_default(): void
    {
        Http::fake([
            'www.boe.es/diario_boe/xml.php*' => Http::response($this->minimalXml(), 200),
        ]);

        $this->artisan('temarios:sync-boe', ['--cuerpo' => 'maestros'])
            ->assertSuccessful();

        // Enrichment is off by default: temas are stored but have no generated_at.
        $this->assertSame(0, TemaOficial::whereNotNull('generated_at')->count());
        $this->assertGreaterThan(0, TemaOficial::count());
    }

    private function minimalXml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <documento>
          <texto>
            <p class="centro_cursiva">Educación Infantil</p>
            <p class="parrafo_2">1. El sistema educativo.</p>
          </texto>
        </documento>
        XML;
    }
}
