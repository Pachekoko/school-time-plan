<?php

namespace Tests\Support;

use App\Domain\GeneradorFranjas;
use App\Enums\Contrato;
use App\Enums\EstadoDisponibilidad;
use App\Enums\TipoEspacio;
use App\Models\Ciclo;
use App\Models\Curso;
use App\Models\Escuela;
use App\Models\Espacio;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\PlanEstudios;
use App\Models\Plantel;
use App\Models\PlantillaHoraria;
use App\Models\Profesor;
use App\Models\Sincronia;
use App\Models\User;
use App\Support\EscuelaActual;

/**
 * Una escuela chica con todo lo que necesita el motor. Deja la escuela como actual.
 *
 * - Planteles CEN y NTE; espacios A1 y A3 (aulas), LAB (laboratorio) y A2 (aula del NTE).
 *   Tiene solución: los dos cursos de Inglés van a la misma hora en las dos aulas del CEN.
 * - Plantilla MAT de lunes a viernes, 4 franjas de 50 min desde las 07:00.
 * - Materias MAT1 (3 h, patrón derivado), QUI1 (2 h, «2 | 1+1» capturado, laboratorio) e ING1 (2 h).
 * - Grupos 1A y 1B del ciclo actual 2027-A.
 * - Profesores P1 (Ana: MAT1, QUI1 e ING1; no disponible Lun 0, preferente Mar 1, sin huecos)
 *   y P2 (Beto: ING1).
 * - Cursos 1A-MAT1 (Ana), 1A-QUI1 (vacante), 1A-ING1 (Beto) y 1B-ING1, los dos de Inglés en
 *   la sincronía ING.
 */
final class EscuelaDePrueba
{
    /**
     * @return array{escuela: Escuela, admin: User, ciclo: Ciclo, plantilla: PlantillaHoraria, planteles: array<string, Plantel>, materias: array<string, Materia>, grupos: array<string, Grupo>, profesores: array<string, Profesor>, sincronia: Sincronia, cursos: array<string, Curso>}
     */
    public static function crear(): array
    {
        $escuela = Escuela::factory()->create(['nombre' => 'Prepa de Prueba']);
        $admin = User::factory()->admin()->create(['escuela_id' => $escuela->id]);
        app(EscuelaActual::class)->establecer($escuela->id);

        $ciclo = Ciclo::factory()->create(['clave' => '2027-A']);
        $escuela->forceFill(['ciclo_actual_id' => $ciclo->id])->save();

        $planteles = [
            'CEN' => Plantel::factory()->create(['clave' => 'CEN', 'nombre' => 'Plantel Centro']),
            'NTE' => Plantel::factory()->create(['clave' => 'NTE', 'nombre' => 'Plantel Norte']),
        ];
        Espacio::factory()->for($planteles['CEN'])->create(['clave' => 'A1', 'nombre' => 'Aula 1', 'capacidad' => 40]);
        Espacio::factory()->for($planteles['CEN'])->create(['clave' => 'A3', 'nombre' => 'Aula 3', 'capacidad' => 40]);
        Espacio::factory()->for($planteles['CEN'])->create(['clave' => 'LAB', 'nombre' => 'Laboratorio', 'tipo' => TipoEspacio::Laboratorio, 'capacidad' => 30]);
        Espacio::factory()->for($planteles['NTE'])->create(['clave' => 'A2', 'nombre' => 'Aula 2', 'capacidad' => 40]);

        $plantilla = PlantillaHoraria::factory()->create(['clave' => 'MAT', 'nombre' => 'Matutino']);
        $plantilla->franjas()->createMany((new GeneradorFranjas)->generar([0, 1, 2, 3, 4], '07:00', 4, 50));

        $plan = PlanEstudios::factory()->create(['clave' => 'BGE', 'nombre' => 'Bachillerato general']);
        $materias = [
            'MAT1' => Materia::factory()->for($plan, 'plan')->create(['clave' => 'MAT1', 'nombre' => 'Matemáticas I', 'horas_semana' => 3]),
            'QUI1' => Materia::factory()->for($plan, 'plan')->create([
                'clave' => 'QUI1', 'nombre' => 'Química I', 'horas_semana' => 2, 'patrones' => [[2], [1, 1]], 'tipo_espacio' => TipoEspacio::Laboratorio,
            ]),
            'ING1' => Materia::factory()->for($plan, 'plan')->create(['clave' => 'ING1', 'nombre' => 'Inglés I', 'horas_semana' => 2]),
        ];

        $grupos = [];
        foreach (['1A' => 30, '1B' => 28] as $clave => $alumnos) {
            $grupos[$clave] = Grupo::factory()->for($ciclo)->for($planteles['CEN'])->for($plantilla, 'plantilla')->for($plan, 'plan')
                ->create(['clave' => $clave, 'alumnos' => $alumnos]);
        }

        $profesores = [
            'P1' => Profesor::factory()->create(['clave' => 'P1', 'nombre' => 'Ana López', 'contrato' => Contrato::TiempoCompleto, 'horas_max' => 20, 'sin_huecos' => true]),
            'P2' => Profesor::factory()->create(['clave' => 'P2', 'nombre' => 'Beto Ruiz', 'horas_max' => 10]),
        ];
        $profesores['P1']->planteles()->attach($planteles['CEN']);
        $profesores['P1']->materias()->attach([$materias['MAT1']->id, $materias['QUI1']->id, $materias['ING1']->id]);
        $profesores['P2']->planteles()->attach($planteles['CEN']);
        $profesores['P2']->materias()->attach($materias['ING1']);

        $franja = fn (int $dia, int $orden) => $plantilla->franjas()->where('dia', $dia)->where('orden', $orden)->firstOrFail();
        $profesores['P1']->disponibilidades()->create(['franja_id' => $franja(0, 0)->id, 'estado' => EstadoDisponibilidad::NoDisponible]);
        $profesores['P1']->disponibilidades()->create(['franja_id' => $franja(1, 1)->id, 'estado' => EstadoDisponibilidad::Preferente]);

        $sincronia = Sincronia::factory()->for($ciclo)->create(['clave' => 'ING', 'nombre' => 'Inglés']);

        $curso = function (string $clave, Grupo $grupo, Materia $materia, array $extra = []) use ($ciclo): Curso {
            $curso = Curso::factory()->for($ciclo)->for($materia)->create(['clave' => $clave, ...$extra]);
            $curso->grupos()->attach($grupo);

            return $curso;
        };

        $cursos = [
            '1A-MAT1' => $curso('1A-MAT1', $grupos['1A'], $materias['MAT1'], ['profesor_id' => $profesores['P1']->id]),
            '1A-QUI1' => $curso('1A-QUI1', $grupos['1A'], $materias['QUI1'], ['vacante' => true]),
            '1A-ING1' => $curso('1A-ING1', $grupos['1A'], $materias['ING1'], ['profesor_id' => $profesores['P2']->id, 'sincronia_id' => $sincronia->id]),
            '1B-ING1' => $curso('1B-ING1', $grupos['1B'], $materias['ING1'], ['sincronia_id' => $sincronia->id]),
        ];

        return compact('escuela', 'admin', 'ciclo', 'plantilla', 'planteles', 'materias', 'grupos', 'profesores', 'sincronia', 'cursos');
    }
}
