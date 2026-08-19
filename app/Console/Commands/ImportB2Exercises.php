<?php

namespace App\Console\Commands;

use App\Models\B2Exercise;
use Illuminate\Console\Command;

class ImportB2Exercises extends Command
{
    protected $signature = 'b2:import-exercises {--file= : Path to JSON file (default: storage/app/b2/b2_exercises_seed.json)}';
    protected $description = 'Importa ejercicios B2 English desde JSON';

    public function handle(): void
    {
        $file = $this->option('file') ?? storage_path('app/b2/b2_exercises_seed.json');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $exercises = json_decode(file_get_contents($file), true);

        if (!$exercises) {
            $this->error('Invalid JSON or empty file.');
            return;
        }

        $this->info("🇬🇧 Importando {$file}...");
        $imported = 0;
        $skipped = 0;

        foreach ($exercises as $ex) {
            $options = $ex['options'] ?? null;
            if (is_array($options)) {
                $options = json_encode($options);
            }

            // Some exercises (listening/writing/speaking) use user_input_instruction as question
            $questionText = $ex['question_text'] ?? $ex['user_input_instruction'] ?? '';

            $created = B2Exercise::firstOrCreate(
                ['question_text' => $questionText, 'skill' => $ex['skill']],
                [
                    'difficulty' => $ex['difficulty'] ?? 3,
                    'type' => $ex['type'] ?? 'general',
                    'options' => $options,
                    'user_input_instruction' => $ex['user_input_instruction'] ?? null,
                    'correct_answer' => is_array($ex['correct_answer'])
                        ? json_encode($ex['correct_answer'])
                        : ($ex['correct_answer'] ?? ''),
                    'explanation' => $ex['explanation'] ?? null,
                    'points_reward' => $ex['points_reward'] ?? 3,
                    'category' => $ex['category'] ?? null,
                    'source' => $ex['source'] ?? null,
                    'is_active' => true,
                ]
            );

            if ($created->wasRecentlyCreated) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        $this->line("✓ {$imported} ejercicios importados, {$skipped} ya existían");
        $this->info('✅ Importación B2 completada');
        $this->table(
            ['Skill', 'Count'],
            B2Exercise::selectRaw('skill, COUNT(*) as count')->groupBy('skill')->get()->map(fn($r) => [$r->skill, $r->count])->toArray()
        );
    }
}
