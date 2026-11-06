<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solo se guardan las franjas preferentes y las no disponibles: sin fila, el profesor
     * está disponible.
     */
    public function up(): void
    {
        Schema::create('disponibilidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->restrictOnDelete();
            $table->foreignId('profesor_id')->constrained('profesores')->cascadeOnDelete();
            $table->foreignId('franja_id')->constrained('franjas')->restrictOnDelete();
            $table->string('estado', 20);
            $table->timestamps();

            $table->unique(['profesor_id', 'franja_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilidades');
    }
};
