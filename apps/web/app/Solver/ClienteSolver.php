<?php

namespace App\Solver;

use App\Solver\Excepciones\ContratoRechazado;
use App\Solver\Excepciones\SolverNoDisponible;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * El motor de horarios por HTTP (`apps/solver`).
 */
final class ClienteSolver
{
    /**
     * En el plan gratuito el motor se duerme: pregunta por /health cada 3 s hasta
     * `solver.espera_s`.
     *
     * @throws SolverNoDisponible
     */
    public function esperarDisponible(): void
    {
        $limite = (int) config('solver.espera_s');

        for ($esperado = 0; ; $esperado += 3) {
            try {
                if ($this->http()->timeout(5)->get('/health')->successful()) {
                    return;
                }
            } catch (ConnectionException) {
                // Sigue esperando.
            }

            if ($esperado >= $limite) {
                throw new SolverNoDisponible("El motor no respondió en {$limite} s.");
            }

            Sleep::for(3)->seconds();
        }
    }

    /**
     * @param  array<string, mixed>  $problema  un SolveRequest
     * @return array<string, mixed> la respuesta del motor
     *
     * @throws SolverNoDisponible
     * @throws ContratoRechazado
     */
    public function resolver(array $problema, int $tiempoLimiteS): array
    {
        try {
            $respuesta = $this->http()
                ->withToken((string) config('solver.token'))
                ->timeout($tiempoLimiteS * 3 + 30)
                ->post('/solve', $problema);
        } catch (ConnectionException $e) {
            throw new SolverNoDisponible('No se pudo conectar con el motor.', previous: $e);
        }

        return match (true) {
            $respuesta->status() === 200 => (array) $respuesta->json(),
            $respuesta->status() === 422 => throw new ContratoRechazado($respuesta->json('detail')),
            $respuesta->status() === 401 => throw new RuntimeException('SOLVER_TOKEN no coincide con el del motor'),
            $respuesta->serverError() => throw new SolverNoDisponible("El motor respondió {$respuesta->status()}."),
            default => throw new RuntimeException("Respuesta inesperada del motor: {$respuesta->status()}"),
        };
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('solver.url'))->acceptJson();
    }
}
