<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('franjas', function (Blueprint $table) {
            // La guía pregunta en cada página del admin si su escuela ya tiene franjas de clase.
            $table->index(['escuela_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::table('franjas', function (Blueprint $table) {
            $table->dropIndex(['escuela_id', 'tipo']);
        });
    }
};
