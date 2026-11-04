<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escuelas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->integer('minutos_traslado')->default(50);
            $table->integer('tiempo_limite_s')->default(60);
            // La llave foránea a ciclos se agrega cuando existe la tabla (Tarea 4).
            $table->unsignedBigInteger('ciclo_actual_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escuelas');
    }
};
