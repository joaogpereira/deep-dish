<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um ciclo de limpeza de mesa: da saída do cliente até a mesa estar pronta.
 *
 * O ciclo em aberto é a linha com 'finalizada_em' nulo — só pode existir um por
 * mesa, garantido pelo MesaLimpezaService, não pelo banco (um índice parcial
 * resolveria, mas a transação do service já serializa por mesa).
 */
class MesaLimpeza extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'mesa_limpeza';

    protected $fillable = [
        'mesa_id',
        'aguardando_desde',
        'iniciada_em',
        'finalizada_em',
        'duracao_segundos',
        'liberada_automaticamente',
    ];

    protected function casts(): array
    {
        return [
            'aguardando_desde' => 'datetime',
            'iniciada_em' => 'datetime',
            'finalizada_em' => 'datetime',
            'duracao_segundos' => 'integer',
            'liberada_automaticamente' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class, 'mesa_id');
    }
}
