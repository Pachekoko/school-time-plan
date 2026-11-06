<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curso_grupo', function (Blueprint $table) {
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('grupo_id')->constrained('grupos')->restrictOnDelete();

            $table->primary(['curso_id', 'grupo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_grupo');
    }
};
