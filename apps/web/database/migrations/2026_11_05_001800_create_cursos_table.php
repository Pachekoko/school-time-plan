<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->restrictOnDelete();
            $table->foreignId('ciclo_id')->constrained('ciclos')->restrictOnDelete();
            $table->foreignId('materia_id')->constrained('materias')->restrictOnDelete();
            $table->foreignId('profesor_id')->nullable()->constrained('profesores')->restrictOnDelete();
            $table->boolean('vacante')->default(false);
            $table->foreignId('sincronia_id')->nullable()->constrained('sincronias')->nullOnDelete();
            $table->unsignedInteger('alumnos')->nullable();
            // Generada a partir de los grupos y la materia: «1A+1B-ING1», «1A-MAT1-2»…
            $table->string('clave');
            $table->timestamps();

            $table->unique(['ciclo_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};
