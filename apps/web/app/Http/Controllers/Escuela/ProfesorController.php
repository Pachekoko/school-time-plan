<?php

namespace App\Http\Controllers\Escuela;

use App\Enums\Contrato;
use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfesorRequest;
use App\Models\Materia;
use App\Models\Plantel;
use App\Models\Profesor;
use App\Models\User;
use App\Support\Borrado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfesorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('escuela/profesores/Index', [
            'profesores' => Profesor::query()
                ->with('user:id,email')
                ->withCount(['planteles', 'materias'])
                ->orderBy('nombre')
                ->get()
                ->map(fn (Profesor $profesor) => [
                    'id' => $profesor->id,
                    'clave' => $profesor->clave,
                    'nombre' => $profesor->nombre,
                    'contrato' => $profesor->contrato->etiqueta(),
                    'horas' => "{$profesor->horas_min}–{$profesor->horas_max}",
                    'planteles' => $profesor->planteles_count,
                    'materias' => $profesor->materias_count,
                    'cuenta' => $profesor->user?->email,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('escuela/profesores/Form', ['profesor' => null, ...$this->opciones()]);
    }

    public function store(ProfesorRequest $request): RedirectResponse
    {
        $nuevo = DB::transaction(function () use ($request): ?User {
            $profesor = Profesor::create($request->datos());
            $profesor->planteles()->sync($request->planteles());
            $profesor->materias()->sync($request->materias());

            return $this->vincularCuenta($profesor, $request->correo());
        });

        $this->mandarEnlace($nuevo);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profesor creado.']);

        return to_route('profesores.index');
    }

    public function edit(Profesor $profesor): Response
    {
        return Inertia::render('escuela/profesores/Form', [
            'profesor' => [
                ...$profesor->only(['id', 'clave', 'nombre', 'contrato', 'horas_min', 'horas_max', 'max_dia', 'sin_huecos']),
                'planteles' => $profesor->planteles()->pluck('planteles.id')->all(),
                'materias' => $profesor->materias()->pluck('materias.id')->all(),
                'cuenta' => $profesor->user?->email,
            ],
            ...$this->opciones(),
        ]);
    }

    public function update(ProfesorRequest $request, Profesor $profesor): RedirectResponse
    {
        $nuevo = DB::transaction(function () use ($request, $profesor): ?User {
            $profesor->update($request->datos());
            $profesor->planteles()->sync($request->planteles());
            $profesor->materias()->sync($request->materias());

            return $this->vincularCuenta($profesor, $request->correo());
        });

        $this->mandarEnlace($nuevo);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profesor guardado.']);

        return to_route('profesores.index');
    }

    public function destroy(Profesor $profesor): RedirectResponse
    {
        Borrado::impedirSiSeUsa($profesor->nombre, ['curso|cursos' => $profesor->cursos()]);

        $profesor->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profesor borrado.']);

        return to_route('profesores.index');
    }

    /**
     * Vincula la cuenta del correo (ya validado por ProfesorRequest). Si no existe, la crea
     * con rol profesor y contraseña al azar, y la devuelve para mandarle el enlace.
     */
    private function vincularCuenta(Profesor $profesor, ?string $correo): ?User
    {
        if ($correo === null || $profesor->user_id !== null) {
            return null;
        }

        $usuario = User::firstWhere('email', $correo);
        $nuevo = null;

        if ($usuario === null) {
            $usuario = new User(['name' => $profesor->nombre, 'email' => $correo, 'password' => Str::password(40)]);
            // Quien entra a definir la contraseña ya probó que el correo es suyo.
            $usuario->forceFill(['escuela_id' => $profesor->escuela_id, 'rol' => Rol::Profesor, 'email_verified_at' => now()])->save();
            $nuevo = $usuario;
        }

        $profesor->user()->associate($usuario)->save();

        return $nuevo;
    }

    private function mandarEnlace(?User $usuario): void
    {
        if ($usuario !== null) {
            Password::sendResetLink(['email' => $usuario->email]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'planteles' => Plantel::query()->orderBy('nombre')->get(['id', 'nombre']),
            'materias' => Materia::query()->orderBy('semestre')->orderBy('clave')->get(['id', 'clave', 'nombre', 'semestre']),
            'contratos' => Contrato::opciones(),
        ];
    }
}
