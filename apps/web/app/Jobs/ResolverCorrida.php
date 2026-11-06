<?php

namespace App\Jobs;

use App\Enums\EstadoCorrida;
use App\Models\Corrida;
use App\Solver\ClienteSolver;
use App\Solver\ConstructorProblema;
use App\Solver\Excepciones\ContratoRechazado;
use App\Solver\GuardarResultado;
use App\Support\EscuelaActual;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Arma el problema de la corrida, se lo manda al motor y guarda la respuesta.
 */
class ResolverCorrida implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public int $timeout = 1000;

    public function __construct(public int $corridaId) {}

    /**
     * Lo más que tarda una corrida desde que entra a la cola o empieza un intento: todos sus
     * intentos con sus esperas. Si pasa más sin avanzar, se atascó.
     */
    public static function duracionMaxima(): int
    {
        $job = new self(0);

        return $job->tries * $job->timeout + array_sum($job->backoff);
    }

    public function handle(ClienteSolver $cliente, ConstructorProblema $constructor, GuardarResultado $guardar): void
    {
        $corrida = Corrida::withoutGlobalScope('escuela')->findOrFail($this->corridaId);

        app(EscuelaActual::class)->usar($corrida->escuela_id, function () use ($corrida, $cliente, $constructor, $guardar): void {
            $corrida->update(['estado' => EstadoCorrida::Resolviendo, 'iniciada_en' => now()]);

            $cliente->esperarDisponible();
            $problema = $constructor->construir($corrida->ciclo()->firstOrFail(), $corrida->parametros);

            try {
                $respuesta = $cliente->resolver($problema, $corrida->tiempo_limite_s);
            } catch (ContratoRechazado $e) {
                Log::error('El motor rechazó el problema de la corrida', ['corrida' => $corrida->id, 'detail' => $e->detail]);
                $this->fail($e);

                return;
            }

            $guardar->guardar($corrida, $respuesta);
        });
    }

    public function failed(?Throwable $e): void
    {
        Corrida::withoutGlobalScope('escuela')->whereKey($this->corridaId)->first()?->update([
            'estado' => EstadoCorrida::Error,
            'mensaje_error' => $e instanceof ContratoRechazado
                ? $e->getMessage()
                : 'No se pudo conectar con el motor de horarios. Reintenta en unos minutos.',
            'terminada_en' => now(),
        ]);
    }
}
