<?php

namespace App\Enums;

enum ModoRegla: string
{
    case Apagada = 'apagada';
    case Preferencia = 'preferencia';
    case Obligatoria = 'obligatoria';
}
