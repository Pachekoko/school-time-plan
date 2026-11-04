<?php

use App\Enums\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified', 'escuela'])->group(function () {
    Route::get('dashboard', fn (Request $request) => match ($request->user()?->rol) {
        Rol::Admin => to_route('escuela.inicio'),
        Rol::Profesor => to_route('mi-disponibilidad'),
        default => abort(403),
    })->name('dashboard');

    Route::middleware('rol:admin')->prefix('escuela')->name('escuela.')->group(function () {
        Route::inertia('/', 'escuela/Inicio')->name('inicio');
    });

    Route::middleware('rol:profesor')->group(function () {
        Route::inertia('mi-disponibilidad', 'MiDisponibilidad')->name('mi-disponibilidad');
    });
});

require __DIR__.'/settings.php';
