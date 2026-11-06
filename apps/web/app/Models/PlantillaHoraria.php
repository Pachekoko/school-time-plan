<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEscuela;
use Database\Factories\PlantillaHorariaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $escuela_id
 * @property string $clave
 * @property string $nombre
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('plantillas')]
#[Fillable(['clave', 'nombre'])]
class PlantillaHoraria extends Model
{
    /** @use HasFactory<PlantillaHorariaFactory> */
    use HasFactory, PerteneceAEscuela;

    /**
     * @return HasMany<Franja, $this>
     */
    public function franjas(): HasMany
    {
        return $this->hasMany(Franja::class, 'plantilla_id');
    }

    /**
     * @return HasMany<Grupo, $this>
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'plantilla_id');
    }
}
