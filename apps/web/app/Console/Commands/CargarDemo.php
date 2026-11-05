<?php

namespace App\Console\Commands;

use App\Enums\Contrato;
use App\Enums\EstadoCiclo;
use App\Enums\EstadoDisponibilidad;
use App\Enums\Rol;
use App\Models\Ciclo;
use App\Models\Escuela;
use App\Models\Espacio;
use App\Models\Franja;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\PlanEstudios;
use App\Models\Plantel;
use App\Models\PlantillaHoraria;
use App\Models\Profesor;
use App\Models\Sincronia;
use App\Models\User;
use App\Support\EscuelaActual;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * La escuela demo «Prepa Horizonte»: el inverso del contrato a partir del problema de
 * demostración del motor. Las claves salen de los ids del JSON.
 */
class CargarDemo extends Command
{
    protected $signature = 'demo:cargar
        {--archivo=../../contracts/fixtures/demo/prepa-horizonte.json : El SolveRequest de la demo}
        {--correo=admin@prepa-horizonte.test : El correo del admin}
        {--password= : La contraseña del admin; vacía, se genera una}';

    protected $description = 'Crea la escuela demo Prepa Horizonte con su admin, a partir del problema de demostración del motor';

    public function handle(): int
    {
        $correo = mb_strtolower(trim((string) $this->option('correo')));

        if (User::query()->where('email', $correo)->exists()) {
            $this->error('La escuela demo ya existe; usa --correo con otro correo.');

            return self::FAILURE;
        }

        $ruta = (string) $this->option('archivo');
        $ruta = str_starts_with($ruta, '/') ? $ruta : base_path($ruta);
        /** @var array<string, mixed> $demo */
        $demo = json_decode((string) file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
        $password = (string) $this->option('password');
        $generada = $password === '';

        if ($generada) {
            $password = Str::password(16, symbols: false);
        }

        DB::transaction(function () use ($demo, $correo, $password): void {
            $escuela = Escuela::create([
                'nombre' => 'Prepa Horizonte',
                'minutos_traslado' => (int) ($demo['parametros']['minutos_traslado'] ?? 50),
                'tiempo_limite_s' => (int) ($demo['parametros']['tiempo_limite_s'] ?? 60),
            ]);
            $admin = new User(['name' => 'Coordinación Horizonte', 'email' => $correo, 'password' => $password]);
            $admin->forceFill(['escuela_id' => $escuela->id, 'rol' => Rol::Admin, 'email_verified_at' => now()])->save();

            app(EscuelaActual::class)->usar($escuela->id, fn () => $this->cargar($escuela, $demo));
        });

        $this->info("Escuela «Prepa Horizonte» lista. Entra con {$correo}.");

        if ($generada) {
            $this->line("Contraseña del admin: {$password} (guárdala: no se vuelve a mostrar)");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $demo
     */
    private function cargar(Escuela $escuela, array $demo): void
    {
        $ciclo = Ciclo::create(['clave' => '2026B', 'estado' => EstadoCiclo::Captura]);
        $escuela->forceFill(['ciclo_actual_id' => $ciclo->id])->save();
        $plan = PlanEstudios::create(['clave' => 'DEMO', 'nombre' => 'Plan de la demo']);

        $plantillas = [];
        foreach ($demo['plantillas'] as $p) {
            $plantilla = PlantillaHoraria::create(['clave' => $p['id'], 'nombre' => $p['nombre']]);
            $plantilla->franjas()->createMany(array_map(
                fn (array $f) => ['dia' => $f['dia'], 'orden' => $f['orden'], 'inicio' => $f['inicio'], 'fin' => $f['fin'], 'tipo' => $f['tipo']],
                $p['franjas'],
            ));
            $plantillas[$p['id']] = $plantilla;
        }

        $planteles = [];
        foreach ($demo['planteles'] as $p) {
            $planteles[$p['id']] = Plantel::create(['clave' => $p['id'], 'nombre' => $p['nombre']]);
        }

        foreach ($demo['espacios'] as $e) {
            Espacio::create([
                'plantel_id' => $planteles[$e['plantel']]->id, 'clave' => $e['id'], 'nombre' => $e['nombre'],
                'tipo' => $e['tipo'], 'capacidad' => $e['capacidad'],
            ]);
        }

        $materias = [];
        foreach ($demo['materias'] as $m) {
            $materias[$m['id']] = Materia::create([
                'plan_id' => $plan->id, 'clave' => $m['id'], 'nombre' => $m['nombre'], 'semestre' => $this->semestre($m['id'], '/^S(\d+)-/'),
                'horas_semana' => $m['horas_semana'], 'patrones' => $m['patrones'], 'tipo_espacio' => $m['tipo_espacio'] ?? 'aula',
            ]);
        }

        /** @var array<string, Grupo> $grupos */
        $grupos = [];
        foreach ($demo['grupos'] as $g) {
            $grupos[$g['id']] = $ciclo->grupos()->create([
                'plantel_id' => $planteles[$g['plantel']]->id, 'plantilla_id' => $plantillas[$g['plantilla']]->id, 'plan_id' => $plan->id,
                'semestre' => $this->semestre($g['nombre'], '/^(\d+)/'), 'clave' => $g['id'], 'alumnos' => $g['alumnos'],
            ]);
        }

        $franjas = Franja::query()->get()->keyBy(fn (Franja $f) => "{$f->plantilla_id}-{$f->dia}-{$f->orden}");
        $profesores = [];
        foreach ($demo['profesores'] as $p) {
            $profesor = Profesor::create([
                'clave' => $p['id'], 'nombre' => $p['nombre'], 'contrato' => Contrato::Asignatura, 'horas_min' => $p['horas_min'] ?? 0,
                'horas_max' => $p['horas_max'], 'max_dia' => $p['max_dia'], 'sin_huecos' => $p['sin_huecos'] ?? false,
            ]);
            $profesor->planteles()->attach(array_map(fn (string $id) => $planteles[$id]->id, $p['planteles']));
            $profesor->materias()->attach(array_map(fn (string $id) => $materias[$id]->id, $p['materias']));

            // Una franja que viene en las dos listas queda como no disponible: es la restricción dura.
            foreach (['preferente' => EstadoDisponibilidad::Preferente, 'no_disponible' => EstadoDisponibilidad::NoDisponible] as $campo => $estado) {
                foreach ($p[$campo] ?? [] as $ref) {
                    $franja = $franjas->get($plantillas[$ref['plantilla']]->id."-{$ref['dia']}-{$ref['orden']}");

                    if ($franja !== null) {
                        $profesor->disponibilidades()->updateOrCreate(['franja_id' => $franja->id], ['estado' => $estado]);
                    }
                }
            }

            $profesores[$p['id']] = $profesor;
        }

        /** @var array<string, Sincronia> $sincronias */
        $sincronias = [];
        foreach ($demo['cursos'] as $c) {
            $sincronia = $c['sincronia'] ?? null;

            if ($sincronia !== null) {
                $sincronias[$sincronia] ??= $ciclo->sincronias()->create(['clave' => $sincronia, 'nombre' => $sincronia]);
            }

            $curso = $ciclo->cursos()->make([
                'materia_id' => $materias[$c['materia']]->id,
                'profesor_id' => isset($c['profesor']) ? $profesores[$c['profesor']]->id : null,
                'vacante' => $c['vacante'] ?? false,
                'sincronia_id' => $sincronia === null ? null : $sincronias[$sincronia]->id,
                'alumnos' => $c['alumnos'] ?? null,
            ]);
            $curso->clave = $c['id'];
            $curso->save();
            $curso->grupos()->attach(array_map(fn (string $id) => $grupos[$id]->id, $c['grupos']));
        }
    }

    /**
     * El semestre que trae el texto («S3-…», «3A CEN»), o 1 si no trae.
     */
    private function semestre(string $texto, string $patron): int
    {
        return preg_match($patron, $texto, $partes) === 1 ? max(1, min(12, (int) $partes[1])) : 1;
    }
}
