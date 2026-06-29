<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Key dates of the interim/teacher process. Events start estimated and
        // superadmin-only; once official, a superadmin confirms + publishes them.
        Schema::create('academic_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 300);
            $table->text('description')->nullable();
            // solicitud | listado_provisional | listado_definitivo | adjudicacion
            // | plazo_alegaciones | resolucion | convocatoria | otro
            $table->string('event_type', 30)->default('otro');
            $table->date('event_date');
            $table->string('time', 10)->nullable();
            $table->foreignId('source_document_id')->nullable()->constrained('detected_documents')->nullOnDelete();
            $table->boolean('is_confirmed')->default(false);
            $table->boolean('is_estimated')->default(true);
            $table->string('affects', 20)->default('todos');       // interinos | funcionarios | opositores | todos
            $table->string('visibility', 20)->default('superadmin_only'); // public | users_only | superadmin_only
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('event_date');
            $table->index(['visibility', 'is_confirmed']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_calendar_events');
    }
};
