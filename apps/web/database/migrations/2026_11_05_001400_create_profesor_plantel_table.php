<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesor_plantel', function (Blueprint $table) {
            $table->foreignId('profesor_id')->constrained('profesores')->cascadeOnDelete();
            $table->foreignId('plantel_id')->constrained('planteles')->restrictOnDelete();

            $table->primary(['profesor_id', 'plantel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesor_plantel');
    }
};
