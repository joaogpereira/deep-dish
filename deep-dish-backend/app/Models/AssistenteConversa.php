<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Uma conversa do assistente do painel, sempre de um único restaurante (#179). */
class AssistenteConversa extends Model
{
    use HasUuids;

    protected $table = 'assistente_conversa';

    protected $fillable = ['restaurante_id'];

    public function restaurante(): BelongsTo
    {
        return $this->belongsTo(Restaurante::class, 'restaurante_id');
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(AssistenteMensagem::class, 'conversa_id')->orderBy('posicao');
    }
}
