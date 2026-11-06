<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franjas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->restrictOnDelete();
            $table->foreignId('plantilla_id')->constrained('plantillas')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia');
            $table->unsignedSmallInteger('orden');
            $table->string('inicio', 5);
            $table->string('fin', 5);
            $table->string('tipo', 10)->default('clase');
            $table->timestamps();

            $table->unique(['plantilla_id', 'dia', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franjas');
    }
};
