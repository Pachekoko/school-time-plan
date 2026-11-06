<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('planes')->restrictOnDelete();
            $table->string('clave', 30);
            $table->string('nombre', 120);
            $table->unsignedTinyInteger('semestre');
            $table->unsignedTinyInteger('horas_semana');
            $table->json('patrones')->nullable();
            $table->string('tipo_espacio', 20)->default('aula');
            $table->timestamps();

            $table->unique(['escuela_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materias');
    }
};
