<?php

namespace Tests\Feature;

use App\Console\Commands\SyncTemariosBoe;
use App\Models\TemaOficial;
use App\Models\TemarioOficial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TemariosBoeXmlTest extends TestCase
{
    use RefreshDatabase;

    private function xml(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <documento>
          <texto>
            <p class="parrafo">Artículo 1. Objeto de la orden.</p>
            <p class="centro_cursiva">Educación Infantil</p>
            <p class="parrafo_2">1. El sistema educativo en la LOE.</p>
            <p class="parrafo_2">1.1 Características.</p>
            <p class="parrafo">1.2 Estructura.</p>
            <p class="parrafo_2">2. La educación infantil: objetivos.</p>
            <p class="centro_cursiva">Educación Primaria</p>
            <p class="parrafo_2">1. Concepto de currículo.</p>
            <p class="centro_cursiva">ANEXO sin temas</p>
          </texto>
        </documento>
        XML;
    }

    public function test_parser_extracts_especialidades_and_main_temas_only(): void
    {
        $esp = (new SyncTemariosBoe())->parseEspecialidades($this->xml());

        $this->assertCount(2, $esp); // Infantil + Primaria; ANEXO dropped (no temas)
        $this->assertSame('Educación Infantil', $esp[0]['especialidad_nombre']);
        $this->assertCount(2, $esp[0]['temas']); // subpoints 1.1 / 1.2 excluded
        $this->assertSame(1, $esp[0]['temas'][0]['numero']);
        $this->assertSame('El sistema educativo en la LOE', $esp[0]['temas'][0]['titulo']);
        $this->assertSame('Educación Primaria', $esp[1]['especialidad_nombre']);
        $this->assertCount(1, $esp[1]['temas']);
    }

    public function test_command_persists_clean_temas_from_xml(): void
    {
        Queue::fake();
        Http::fake(['www.boe.es/diario_boe/xml.php*' => Http::response($this->xml(), 200)]);

        $this->artisan('temarios:sync-boe', ['--cuerpo' => 'maestros'])
            ->assertSuccessful();

        // 2 especialidades → 2 temarios; 3 main temas total (subpoints excluded).
        $this->assertSame(2, TemarioOficial::count());
        $this->assertSame(3, TemaOficial::count());
        $this->assertSame(2, TemarioOficial::where('especialidad_nombre', 'Educación Infantil')->first()->total_temas);
    }
}
