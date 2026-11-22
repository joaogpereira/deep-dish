<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma mensagem da conversa, no formato que o OpenRouter usa (#179).
 *
 * Guarda também as trocas de ferramenta, para registro. O que volta para o
 * modelo como contexto é só o texto de usuário e assistente — ver
 * AssistenteChatController::historico().
 */
class AssistenteMensagem extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'assistente_mensagem';

    protected $fillable = ['conversa_id', 'posicao', 'role', 'content', 'tool_calls', 'tool_call_id', 'name'];

    protected function casts(): array
    {
        return [
            'posicao' => 'integer',
            'tool_calls' => 'array',
        ];
    }
}
