<?php

namespace App\Console\Commands;

use App\Models\TestRazonamiento;
use App\Models\FlashcardUe;
use Illuminate\Console\Command;

class ImportEpsoContent extends Command
{
    protected $signature = 'epso:import-content';

    protected $description = 'Importa preguntas EPSO y flashcards UE desde JSONs';

    public function handle()
    {
        $this->info('🚀 Iniciando importación de contenido EPSO...');

        $this->importarVerbales();
        $this->importarNumericas();
        $this->importarFlashcards();

        $this->info('✅ Importación completada correctamente');
    }

    private function importarVerbales()
    {
        $this->info('📝 Importando preguntas verbales...');

        $json = file_get_contents(storage_path('app/epso/epso_preguntas_verbales.json'));
        $preguntas = json_decode($json, true);

        foreach ($preguntas as $pregunta) {
            TestRazonamiento::firstOrCreate(
                ['pregunta' => $pregunta['pregunta']],
                [
                    'tipo' => 'verbal',
                    'opciones' => $pregunta['opciones'],
                    'respuesta_correcta' => $pregunta['respuesta_correcta'],
                    'tiempo_esperado_segundos' => $pregunta['tiempo_esperado_segundos'],
                    'explicacion' => $pregunta['explicacion'],
                    'dificultad' => $pregunta['dificultad'],
                    'tipo_error' => $pregunta['tipo_error'] ?? null,
                ]
            );
        }

        $this->line('✓ ' . count($preguntas) . ' preguntas verbales importadas');
    }

    private function importarNumericas()
    {
        $this->info('📊 Importando preguntas numéricas...');

        $json = file_get_contents(storage_path('app/epso/epso_preguntas_numericas.json'));
        $preguntas = json_decode($json, true);

        foreach ($preguntas as $pregunta) {
            TestRazonamiento::firstOrCreate(
                ['pregunta' => $pregunta['pregunta']],
                [
                    'tipo' => 'numerico',
                    'opciones' => $pregunta['opciones'],
                    'respuesta_correcta' => $pregunta['respuesta_correcta'],
                    'tiempo_esperado_segundos' => $pregunta['tiempo_esperado_segundos'],
                    'explicacion' => $pregunta['explicacion'],
                    'dificultad' => $pregunta['dificultad'],
                    'tipo_error' => $pregunta['tipo_error'] ?? null,
                ]
            );
        }

        $this->line('✓ ' . count($preguntas) . ' preguntas numéricas importadas');
    }

    private function importarFlashcards()
    {
        $this->info('🎴 Importando flashcards UE...');

        $json = file_get_contents(storage_path('app/epso/epso_flashcards_ue.json'));
        $flashcards = json_decode($json, true);

        foreach ($flashcards as $card) {
            FlashcardUe::firstOrCreate(
                ['frente' => $card['frente']],
                [
                    'categoria' => $card['categoria'],
                    'reverso' => $card['reverso'],
                    'dificultad' => $card['dificultad'],
                    'repeticiones' => 0,
                    'fecha_proxima_repaso' => now(),
                ]
            );
        }

        $this->line('✓ ' . count($flashcards) . ' flashcards importadas');
    }
}
