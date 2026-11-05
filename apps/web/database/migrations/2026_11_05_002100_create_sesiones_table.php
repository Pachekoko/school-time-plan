<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada bloque de un curso que acomodó el motor (o que quedó sin acomodar).
     */
    public function up(): void
    {
        Schema::create('sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->restrictOnDelete();
            $table->foreignId('corrida_id')->constrained('corridas')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->unsignedSmallInteger('patron')->default(0);
            $table->unsignedSmallInteger('bloque');
            $table->unsignedTinyInteger('dia')->nullable();
            $table->unsignedSmallInteger('orden_inicio')->nullable();
            $table->unsignedSmallInteger('duracion');
            $table->foreignId('espacio_id')->nullable()->constrained('espacios')->nullOnDelete();
            $table->foreignId('profesor_id')->nullable()->constrained('profesores')->nullOnDelete();
            $table->boolean('fijada')->default(false);
            $table->boolean('sin_acomodar')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones');
    }
};
