<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('test_razonamiento', function (Blueprint $table) {
            $table->string('nivel', 10)->default('ambos')->after('tipo');
            $table->index('nivel');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE test_razonamiento DROP CONSTRAINT IF EXISTS test_razonamiento_tipo_check");
            DB::statement("ALTER TABLE test_razonamiento ADD CONSTRAINT test_razonamiento_tipo_check CHECK (tipo IN ('verbal', 'numerico', 'abstracto', 'sjt'))");
            DB::statement("ALTER TABLE sesion_test DROP CONSTRAINT IF EXISTS sesion_test_tipo_razonamiento_check");
            DB::statement("ALTER TABLE sesion_test ADD CONSTRAINT sesion_test_tipo_razonamiento_check CHECK (tipo_razonamiento IN ('verbal', 'numerico', 'abstracto', 'sjt'))");
        }

        // Mark existing exercises as 'ambos'
        DB::table('test_razonamiento')->update(['nivel' => 'ambos']);
    }

    public function down(): void
    {
        Schema::table('test_razonamiento', function (Blueprint $table) {
            $table->dropIndex(['nivel']);
            $table->dropColumn('nivel');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE test_razonamiento DROP CONSTRAINT IF EXISTS test_razonamiento_tipo_check");
            DB::statement("ALTER TABLE test_razonamiento ADD CONSTRAINT test_razonamiento_tipo_check CHECK (tipo IN ('verbal', 'numerico', 'abstracto'))");
            DB::statement("ALTER TABLE sesion_test DROP CONSTRAINT IF EXISTS sesion_test_tipo_razonamiento_check");
            DB::statement("ALTER TABLE sesion_test ADD CONSTRAINT sesion_test_tipo_razonamiento_check CHECK (tipo_razonamiento IN ('verbal', 'numerico', 'abstracto'))");
        }
    }
};
