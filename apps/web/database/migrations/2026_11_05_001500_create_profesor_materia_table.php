<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesor_materia', function (Blueprint $table) {
            $table->foreignId('profesor_id')->constrained('profesores')->cascadeOnDelete();
            $table->foreignId('materia_id')->constrained('materias')->restrictOnDelete();

            $table->primary(['profesor_id', 'materia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesor_materia');
    }
};
