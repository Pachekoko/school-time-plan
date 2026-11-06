<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cursos que el motor coloca a la misma hora (por ejemplo, los niveles de Inglés).
     */
    public function up(): void
    {
        Schema::create('sincronias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->restrictOnDelete();
            $table->foreignId('ciclo_id')->constrained('ciclos')->restrictOnDelete();
            $table->string('clave', 30);
            $table->string('nombre', 120);
            $table->timestamps();

            $table->unique(['ciclo_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sincronias');
    }
};
