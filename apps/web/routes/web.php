<?php

use App\Enums\Rol;
use App\Http\Controllers\Escuela\CicloController;
use App\Http\Controllers\Escuela\CursoController;
use App\Http\Controllers\Escuela\DisponibilidadController;
use App\Http\Controllers\Escuela\EspacioController;
use App\Http\Controllers\Escuela\FranjaController;
use App\Http\Controllers\Escuela\GrupoController;
use App\Http\Controllers\Escuela\MateriaController;
use App\Http\Controllers\Escuela\PlanController;
use App\Http\Controllers\Escuela\PlantelController;
use App\Http\Controllers\Escuela\PlantillaController;
use App\Http\Controllers\Escuela\ProfesorController;
use App\Http\Controllers\Escuela\SincroniaController;
use App\Http\Controllers\MiDisponibilidadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified', 'escuela'])->group(function () {
    Route::get('dashboard', fn (Request $request) => match ($request->user()?->rol) {
        Rol::Admin => to_route('escuela.inicio'),
        Rol::Profesor => to_route('mi-disponibilidad'),
        default => abort(403),
    })->name('dashboard');

    Route::middleware('rol:admin')->group(function () {
        Route::inertia('escuela', 'escuela/Inicio')->name('escuela.inicio');

        Route::resource('planteles', PlantelController::class)->except('show')->parameters(['planteles' => 'plantel']);
        Route::resource('espacios', EspacioController::class)->except('show')->parameters(['espacios' => 'espacio']);

        Route::resource('ciclos', CicloController::class)->except('show')->parameters(['ciclos' => 'ciclo']);
        Route::post('ciclos/{ciclo}/activar', [CicloController::class, 'activar'])->name('ciclos.activar');

        Route::resource('plantillas', PlantillaController::class)->except('show')->parameters(['plantillas' => 'plantilla']);
        Route::get('plantillas/{plantilla}/franjas', [FranjaController::class, 'index'])->name('plantillas.franjas');
        Route::post('plantillas/{plantilla}/franjas/generar', [FranjaController::class, 'generar'])->name('plantillas.generar');
        Route::put('franjas/{franja}', [FranjaController::class, 'update'])->name('franjas.update');
        Route::delete('franjas/{franja}', [FranjaController::class, 'destroy'])->name('franjas.destroy');

        Route::resource('planes', PlanController::class)->except('show')->parameters(['planes' => 'plan']);
        Route::resource('materias', MateriaController::class)->except('show')->parameters(['materias' => 'materia']);

        Route::resource('profesores', ProfesorController::class)->except('show')->parameters(['profesores' => 'profesor']);
        Route::get('profesores/{profesor}/disponibilidad', [DisponibilidadController::class, 'edit'])->name('profesores.disponibilidad');
        Route::put('profesores/{profesor}/disponibilidad', [DisponibilidadController::class, 'update'])->name('profesores.guardar-disponibilidad');

        Route::middleware('ciclo')->group(function () {
            Route::resource('grupos', GrupoController::class)->except('show')->parameters(['grupos' => 'grupo']);
            Route::resource('cursos', CursoController::class)->except('show')->parameters(['cursos' => 'curso']);
            Route::resource('sincronias', SincroniaController::class)->except('show')->parameters(['sincronias' => 'sincronia']);
        });
    });

    Route::middleware('rol:profesor')->group(function () {
        Route::get('mi-disponibilidad', [MiDisponibilidadController::class, 'edit'])->name('mi-disponibilidad');
        Route::put('mi-disponibilidad', [MiDisponibilidadController::class, 'update'])->name('guardar-mi-disponibilidad');
    });
});

require __DIR__.'/settings.php';
