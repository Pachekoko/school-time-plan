<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('escuela_id')->nullable()->after('id')->constrained('escuelas')->restrictOnDelete();
            // Sin rol explícito, el de menos permisos.
            $table->string('rol', 20)->default('alumno')->after('escuela_id');
            // La llave foránea a grupos se agrega cuando existe la tabla (Tarea 5).
            $table->unsignedBigInteger('grupo_id')->nullable()->after('rol');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('escuela_id');
            $table->dropColumn(['rol', 'grupo_id']);
        });
    }
};
