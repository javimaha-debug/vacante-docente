<?php

namespace Tests\Feature;

use App\Models\Proceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateProcesosTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_past_year_procesos_as_closed(): void
    {
        $this->seed(\Database\Seeders\CcaaSeeder::class);
        $this->seed(\Database\Seeders\ColectivoSeeder::class);

        $this->artisan('procesos:create', ['anyo' => 2024])->assertSuccessful();

        $this->assertSame(6, Proceso::where('curso', '2024-2025')->count());
        $this->assertSame(6, Proceso::where('curso', '2024-2025')->where('estado', 'cerrado')->count());

        // Idempotent.
        $this->artisan('procesos:create', ['anyo' => 2024])->assertSuccessful();
        $this->assertSame(6, Proceso::where('curso', '2024-2025')->count());
    }

    public function test_it_accepts_custom_curso_and_estado(): void
    {
        $this->seed(\Database\Seeders\CcaaSeeder::class);
        $this->seed(\Database\Seeders\ColectivoSeeder::class);

        $this->artisan('procesos:create', ['anyo' => 2023, '--curso' => '2023-24', '--estado' => 'publicado'])
            ->assertSuccessful();

        $this->assertSame(6, Proceso::where('curso', '2023-24')->where('estado', 'publicado')->count());
    }

    public function test_it_rejects_invalid_estado(): void
    {
        $this->seed(\Database\Seeders\CcaaSeeder::class);
        $this->seed(\Database\Seeders\ColectivoSeeder::class);

        $this->artisan('procesos:create', ['anyo' => 2024, '--estado' => 'loquesea'])->assertFailed();
    }
}
