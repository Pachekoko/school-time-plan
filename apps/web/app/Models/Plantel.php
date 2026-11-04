<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEscuela;
use Database\Factories\PlantelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $escuela_id
 * @property string $clave
 * @property string $nombre
 * @property string|null $direccion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('planteles')]
#[Fillable(['clave', 'nombre', 'direccion'])]
class Plantel extends Model
{
    /** @use HasFactory<PlantelFactory> */
    use HasFactory, PerteneceAEscuela;
}
