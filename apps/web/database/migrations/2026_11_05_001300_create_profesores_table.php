<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->restrictOnDelete();
            $table->string('clave', 30);
            $table->string('nombre', 120);
            $table->string('contrato', 20);
            $table->unsignedSmallInteger('horas_min')->default(0);
            $table->unsignedSmallInteger('horas_max');
            $table->unsignedTinyInteger('max_dia');
            $table->boolean('sin_huecos')->default(false);
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['escuela_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesores');
    }
};
