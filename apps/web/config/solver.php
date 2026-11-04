<?php

return [

    /*
    | Motor de horarios (apps/solver). La web le manda el problema por HTTP con un token
    | compartido y espera hasta `espera_s` segundos a que despierte (cold start de Render).
    */

    'url' => rtrim((string) env('SOLVER_URL', 'http://localhost:8000'), '/'),

    'token' => env('SOLVER_TOKEN'),

    'workers' => (int) env('SOLVER_WORKERS', 4),

    'espera_s' => (int) env('SOLVER_ESPERA_S', 90),

];
