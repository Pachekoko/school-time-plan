<?php

namespace App\Solver\Excepciones;

use RuntimeException;

/**
 * El motor no respondió (dormido, caído o con error 5xx). El job lo reintenta.
 */
class SolverNoDisponible extends RuntimeException {}
