<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escuelas', function (Blueprint $table) {
            $table->foreign('ciclo_actual_id')->references('id')->on('ciclos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('escuelas', function (Blueprint $table) {
            $table->dropForeign(['ciclo_actual_id']);
        });
    }
};
