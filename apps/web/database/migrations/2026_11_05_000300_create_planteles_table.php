<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planteles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->restrictOnDelete();
            $table->string('clave', 30);
            $table->string('nombre', 120);
            $table->string('direccion')->nullable();
            $table->timestamps();

            $table->unique(['escuela_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planteles');
    }
};
