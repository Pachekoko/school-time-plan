<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corridas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->restrictOnDelete();
            $table->foreignId('ciclo_id')->constrained('ciclos')->restrictOnDelete();
            $table->string('estado', 20)->default('pendiente');
            $table->json('parametros');
            $table->unsignedSmallInteger('tiempo_limite_s');
            $table->bigInteger('puntaje')->nullable();
            $table->json('desglose')->nullable();
            $table->json('diagnostico')->nullable();
            $table->json('estadisticas')->nullable();
            $table->text('mensaje_error')->nullable();
            $table->foreignId('origen_corrida_id')->nullable()->constrained('corridas')->nullOnDelete();
            $table->boolean('publicada')->default(false);
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('iniciada_en')->nullable();
            $table->timestamp('terminada_en')->nullable();
            $table->timestamps();

            $table->index(['ciclo_id', 'created_at']);
        });

        // Una sola corrida en cola o resolviendo por ciclo. El índice parcial vale igual en
        // SQLite y en PostgreSQL.
        DB::statement("CREATE UNIQUE INDEX corridas_una_activa ON corridas (ciclo_id) WHERE estado IN ('pendiente', 'resolviendo')");
    }

    public function down(): void
    {
        Schema::dropIfExists('corridas');
    }
};
