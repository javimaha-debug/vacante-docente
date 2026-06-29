<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CcaaSeeder::class,
            ColectivoSeeder::class,
            SpecialtySeeder::class,
            VacancySeeder::class,
            PlanesSeeder::class,
            SuperAdminSeeder::class,
            DocumentMonitorSeeder::class,
            NormativaSeeder::class,
            ConvocatoriaSeeder::class,
            ResourceLinksSeeder::class,
        ]);
    }
}
