<?php

namespace Database\Seeders;

use App\Models\Ccaa;
use App\Models\Colectivo;
use Illuminate\Database\Seeder;

class ColectivoSeeder extends Seeder
{
    public function run(): void
    {
        $cv = Ccaa::where('code', 'CV')->first();

        if (! $cv) {
            $this->command?->error('CCAA "CV" not found. Run CcaaSeeder first.');

            return;
        }

        // code => [body => name] for the Comunitat Valenciana placement collectives.
        $definitions = [
            'INTERINO' => [
                'name' => 'Personal funcionari interí',
                'bodies' => ['SECUNDARIA', 'MAESTROS'],
            ],
            'SUPRIMIDO' => [
                'name' => 'Personal amb lloc suprimit',
                'bodies' => ['SECUNDARIA', 'MAESTROS'],
            ],
            'COMISION_SERVICIO' => [
                'name' => 'Comissió de serveis',
                'bodies' => ['SECUNDARIA', 'MAESTROS'],
            ],
            'PRACTICAS' => [
                'name' => 'Personal funcionari en pràctiques',
                'bodies' => ['SECUNDARIA', 'MAESTROS'],
            ],
        ];

        $count = 0;
        foreach ($definitions as $code => $def) {
            foreach ($def['bodies'] as $body) {
                Colectivo::updateOrCreate(
                    ['ccaa_id' => $cv->id, 'code' => $code, 'body' => $body],
                    ['name' => $def['name']],
                );
                $count++;
            }
        }

        $this->command?->info("Seeded {$count} colectivo records for CV.");
    }
}
