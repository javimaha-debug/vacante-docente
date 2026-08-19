<?php

namespace App\Console\Commands;

use App\Models\B2Exercise;
use Illuminate\Console\Command;

class B2ImportBulkExercises extends Command
{
    protected $signature = 'b2:import-bulk {--file= : Path to JSON file}';
    protected $description = 'Import bulk B2 exercises from JSON';

    public function handle(): int
    {
        $path = $this->option('file') ?? storage_path('app/b2/b2_exercises_bulk_seed.json');

        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return 1;
        }

        $exercises = json_decode(file_get_contents($path), true);

        if (!$exercises || !is_array($exercises)) {
            $this->error('Invalid JSON.');
            return 1;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($exercises as $ex) {
            $questionText = $ex['question_text'] ?? $ex['user_input_instruction'] ?? '';

            if (empty($questionText)) {
                $this->warn("Skipping exercise with no question text.");
                $skipped++;
                continue;
            }

            $options = $ex['options'] ?? null;
            if (is_array($options)) {
                $options = json_encode($options);
            }

            $existing = B2Exercise::where('skill', $ex['skill'] ?? 'grammar')
                ->where('question_text', $questionText)
                ->exists();

            if ($existing) {
                $skipped++;
                continue;
            }

            B2Exercise::create([
                'skill' => $ex['skill'] ?? 'grammar',
                'difficulty' => $ex['difficulty'] ?? 3,
                'type' => $ex['type'] ?? 'multiple_choice',
                'question_text' => $questionText,
                'options' => $options,
                'correct_answer' => $ex['correct_answer'] ?? null,
                'explanation' => $ex['explanation'] ?? null,
                'points_reward' => $ex['points_reward'] ?? 2,
                'category' => $ex['category'] ?? null,
                'source' => $ex['source'] ?? null,
                'is_active' => true,
            ]);
            $imported++;
        }

        $this->info("Done. Imported: {$imported}, Skipped (duplicates/invalid): {$skipped}");
        $total = B2Exercise::count();
        $this->info("Total exercises in DB: {$total}");

        return 0;
    }
}
