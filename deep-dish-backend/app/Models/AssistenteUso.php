<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Tokens gastos por uma pergunta ao assistente, com sucesso ou não (#179). */
class AssistenteUso extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'assistente_uso';

    protected $fillable = ['restaurante_id', 'conversa_id', 'tokens_prompt', 'tokens_resposta', 'tokens_total', 'ok'];

    protected function casts(): array
    {
        return [
            'tokens_prompt' => 'integer',
            'tokens_resposta' => 'integer',
            'tokens_total' => 'integer',
            'ok' => 'boolean',
        ];
    }
}
