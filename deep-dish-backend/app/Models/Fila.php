<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fila extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public const STATUS_ABERTA = 'aberta';

    public const STATUS_ENCERRADA = 'encerrada';

    /**
     * A janela de quem chega agora — fila de restaurante é "me põe na lista".
     *
     * Existe uma fila por restaurante por horário, e a posição é contada dentro
     * dela. Se cada pessoa entrasse com o instante exato do clique, cada uma
     * teria a sua própria fila e todas seriam "posição 1"; por isso quem chega
     * agora compartilha a janela da hora corrente.
     */
    public static function janelaAtual(): Carbon
    {
        return now()->utc()->startOfHour();
    }

    protected $table = 'fila';

    protected $fillable = [
        'restaurante_id',
        'horario_reserva',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'horario_reserva' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function restaurante(): BelongsTo
    {
        return $this->belongsTo(Restaurante::class, 'restaurante_id');
    }

    public function clienteFilas(): HasMany
    {
        return $this->hasMany(ClienteFila::class, 'fila_id');
    }
}
