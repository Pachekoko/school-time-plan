<?php

namespace App\Models;

use App\Enums\TipoEspacio;
use App\Models\Concerns\PerteneceAEscuela;
use Database\Factories\EspacioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $escuela_id
 * @property int $plantel_id
 * @property string $clave
 * @property string $nombre
 * @property TipoEspacio $tipo
 * @property int $capacidad
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('espacios')]
#[Fillable(['plantel_id', 'clave', 'nombre', 'tipo', 'capacidad'])]
class Espacio extends Model
{
    /** @use HasFactory<EspacioFactory> */
    use HasFactory, PerteneceAEscuela;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoEspacio::class,
            'capacidad' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Plantel, $this>
     */
    public function plantel(): BelongsTo
    {
        return $this->belongsTo(Plantel::class);
    }
}
