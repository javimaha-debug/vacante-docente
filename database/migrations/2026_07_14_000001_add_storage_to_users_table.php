<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_used_bytes')->default(0);
            $table->unsignedBigInteger('storage_limit_bytes')->default(2147483648); // 2 GB
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['storage_used_bytes', 'storage_limit_bytes']);
        });
    }
};
