<?php

namespace App\Console\Commands;

use App\Models\B2Resource;
use Illuminate\Console\Command;

class B2ImportResources extends Command
{
    protected $signature = 'b2:import-resources {--file= : Path to JSON file}';
    protected $description = 'Import B2 resources (videos, articles, podcasts) from JSON';

    public function handle(): int
    {
        $path = $this->option('file') ?? storage_path('app/b2/b2_resources_seed.json');

        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return 1;
        }

        $resources = json_decode(file_get_contents($path), true);

        if (!$resources || !is_array($resources)) {
            $this->error('Invalid JSON.');
            return 1;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($resources as $res) {
            if (empty($res['title']) || empty($res['url'])) {
                $skipped++;
                continue;
            }

            $exists = B2Resource::where('url', $res['url'])->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            B2Resource::create([
                'type' => $res['type'] ?? 'reference',
                'title' => $res['title'],
                'description' => $res['description'] ?? '',
                'url' => $res['url'],
                'level' => $res['level'] ?? 'B2',
                'category' => $res['category'] ?? null,
                'duration_minutes' => $res['duration_minutes'] ?? null,
                'source' => $res['source'] ?? null,
                'icon_emoji' => $res['icon_emoji'] ?? null,
                'is_active' => true,
            ]);
            $imported++;
        }

        $this->info("Done. Imported: {$imported}, Skipped (duplicates): {$skipped}");
        $total = B2Resource::count();
        $this->info("Total resources in DB: {$total}");

        return 0;
    }
}
