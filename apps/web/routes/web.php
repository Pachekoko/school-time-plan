<?php

use App\Enums\Rol;
use App\Http\Controllers\Escuela\EspacioController;
use App\Http\Controllers\Escuela\PlantelController;
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
    });

    Route::middleware('rol:profesor')->group(function () {
        Route::inertia('mi-disponibilidad', 'MiDisponibilidad')->name('mi-disponibilidad');
    });
});

require __DIR__.'/settings.php';
