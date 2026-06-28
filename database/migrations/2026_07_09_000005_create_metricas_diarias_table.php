<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metricas_diarias', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->integer('usuarios_total')->default(0);
            $table->integer('usuarios_nuevos')->default(0);
            $table->integer('usuarios_activos_7d')->default(0);
            $table->integer('usuarios_free')->default(0);
            $table->integer('usuarios_de_pago')->default(0);
            $table->decimal('mrr', 10, 2)->default(0);
            $table->decimal('arr', 10, 2)->default(0);
            $table->integer('nuevos_interino')->default(0);
            $table->integer('nuevos_opositor')->default(0);
            $table->integer('nuevos_docente_pro')->default(0);
            $table->integer('nuevos_todo_en_uno')->default(0);
            $table->integer('churn_count')->default(0);
            $table->decimal('churn_mrr', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metricas_diarias');
    }
};
