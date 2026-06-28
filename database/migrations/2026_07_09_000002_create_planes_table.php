<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->decimal('precio_mensual', 8, 2)->default(0);
            $table->decimal('precio_anual', 8, 2)->nullable();
            $table->decimal('precio_temporada', 8, 2)->nullable();
            $table->string('stripe_price_id_mensual')->nullable();
            $table->string('stripe_price_id_anual')->nullable();
            $table->boolean('activo')->default(true);
            $table->json('features')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
