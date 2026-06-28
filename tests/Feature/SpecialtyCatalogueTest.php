<?php

namespace Tests\Feature;

use App\Models\Ccaa;
use App\Models\Specialty;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalogue(): void
    {
        Ccaa::create(['code' => 'CV', 'name' => 'Comunitat Valenciana', 'is_active' => true]);
        (new SpecialtySeeder())->run();
    }

    public function test_real_gva_codes_are_present_and_active(): void
    {
        $this->seedCatalogue();

        foreach (['201', '206', '218', '2B7', '3A1', '418', '5D6'] as $code) {
            $this->assertTrue(
                Specialty::where('code', $code)->where('is_active', true)->exists(),
                "GVA code {$code} should exist and be active"
            );
        }

        // Maestros habilitations, including the added Francés (127).
        foreach (['120', '121', '122', '123', '124', '125', '126', '127'] as $code) {
            $this->assertTrue(
                Specialty::where('code', $code)->where('body', 'Maestros')->where('is_active', true)->exists()
            );
        }
    }

    public function test_only_one_active_orientacion_educativa(): void
    {
        // Pre-seed the fabricated duplicate to prove the seeder deactivates it.
        $cv = Ccaa::create(['code' => 'CV', 'name' => 'CV', 'is_active' => true]);
        Specialty::create([
            'code' => '117', 'codigo' => '117', 'name' => 'Orientación Educativa',
            'body' => 'Profesores de Enseñanza Secundaria', 'education_level' => 'secundaria',
            'cuerpo' => 'SECUNDARIA', 'ccaa_id' => $cv->id, 'is_active' => true,
        ]);

        (new SpecialtySeeder())->run();

        $active = Specialty::where('is_active', true)
            ->where('name', 'like', 'Orientaci%Educativa')
            ->get();

        $this->assertCount(1, $active);
        $this->assertSame('218', $active->first()->code);
        $this->assertFalse(Specialty::where('code', '117')->value('is_active'));
    }
}
