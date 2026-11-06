<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->restrictOnDelete();
            $table->foreignId('ciclo_id')->constrained('ciclos')->restrictOnDelete();
            $table->foreignId('plantel_id')->constrained('planteles')->restrictOnDelete();
            $table->foreignId('plantilla_id')->constrained('plantillas')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('planes')->restrictOnDelete();
            $table->unsignedTinyInteger('semestre');
            $table->string('clave', 30);
            $table->unsignedInteger('alumnos');
            $table->timestamps();

            $table->unique(['ciclo_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
