<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mesa extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_LIVRE = 'livre';

    public const STATUS_OCUPADA = 'ocupada';

    public const STATUS_AGUARDANDO_LIMPEZA = 'aguardando_limpeza';

    public const STATUS_EM_LIMPEZA = 'em_limpeza';

    public const STATUS_BLOQUEADA = 'bloqueada';

    /**
     * Os dois estados entre "cliente saiu" e "mesa pronta" (#171). A mesa nao
     * recebe ninguem aqui — mesasDisponiveis() procura 'livre' —, mas continua
     * contando na capacidade do salao, porque vai voltar.
     */
    public const STATUS_LIMPEZA = [
        self::STATUS_AGUARDANDO_LIMPEZA,
        self::STATUS_EM_LIMPEZA,
    ];

    protected $table = 'mesa';

    protected $fillable = [
        'restaurante_id',
        'numero',
        'status',
        'capacidade',
        'confirmacao',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function restaurante(): BelongsTo
    {
        return $this->belongsTo(Restaurante::class, 'restaurante_id');
    }

    public function clienteMesas(): HasMany
    {
        return $this->hasMany(ClienteMesa::class, 'mesa_id');
    }

    /** Ciclos de limpeza desta mesa (#171); o aberto tem 'finalizada_em' nulo. */
    public function limpezas(): HasMany
    {
        return $this->hasMany(MesaLimpeza::class, 'mesa_id');
    }
}
