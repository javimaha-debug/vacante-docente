<?php

namespace Database\Seeders;

use App\Models\Ccaa;
use Illuminate\Database\Seeder;

class CcaaSeeder extends Seeder
{
    public function run(): void
    {
        $ccaas = [
            ['code' => 'CV', 'name' => 'Comunitat Valenciana', 'is_active' => true],
        ];

        foreach ($ccaas as $ccaa) {
            // Idempotent: keyed by the unique `code`.
            Ccaa::updateOrCreate(['code' => $ccaa['code']], $ccaa);
        }

        $this->command?->info('Seeded '.count($ccaas).' CCAA record(s).');
    }
}
