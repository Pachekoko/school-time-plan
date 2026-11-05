<?php

namespace App\Excel;

use InvalidArgumentException;

/**
 * Una celda del Excel que no se puede leer como lo que pide su columna. El mensaje va en
 * español y se muestra tal cual en la vista previa.
 */
class ValorInvalido extends InvalidArgumentException {}
