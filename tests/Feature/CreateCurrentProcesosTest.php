<?php

namespace Tests\Feature;

use App\Models\Proceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateCurrentProcesosTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_six_current_procesos_idempotently(): void
    {
        $this->seed(\Database\Seeders\CcaaSeeder::class);
        $this->seed(\Database\Seeders\ColectivoSeeder::class);

        $this->artisan('procesos:create-current')->assertSuccessful();

        $this->assertSame(6, Proceso::where('curso', '2026-2027')->count());
        $this->assertSame(2, Proceso::where('curso', '2026-2027')->where('estado', 'publicado')->count());
        $this->assertSame(4, Proceso::where('curso', '2026-2027')->where('estado', 'pendiente')->count());

        // Running again must not duplicate.
        $this->artisan('procesos:create-current')->assertSuccessful();
        $this->assertSame(6, Proceso::where('curso', '2026-2027')->count());
    }

    public function test_it_fails_gracefully_without_cv(): void
    {
        $this->artisan('procesos:create-current')->assertFailed();
    }
}
