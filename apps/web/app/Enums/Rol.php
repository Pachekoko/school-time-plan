<?php

namespace App\Enums;

enum Rol: string
{
    case Admin = 'admin';
    case Profesor = 'profesor';
    case Alumno = 'alumno';
}
