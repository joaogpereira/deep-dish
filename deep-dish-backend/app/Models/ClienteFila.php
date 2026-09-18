<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteFila extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_SAIDA_DESISTIU = 'desistiu';

    public const STATUS_SAIDA_ATENDIDO = 'atendido';

    public const STATUS_SAIDA_REMOVIDO = 'removido'; // ação administrativa (item 6)

    public const STATUS_SAIDA_EXPIRADO = 'expirado'; // no-show: foi chamado e não apareceu (#12)

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'clientefila';

    protected $fillable = [
        'fila_id',
        'cliente_id',
        'qntd_pessoas',
    ];

    // 'chamado_em' e 'clientemesa_id' ficam fora do $fillable como os demais
    // campos de histórico: quem escreve é registrarChamada(), não um request.

    // 'posicao' saiu do $appends de propósito: dispara um COUNT por registro.
    // Use ->append('posicao') apenas onde a posição é realmente necessária.

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'chamado_em' => 'datetime',
            'saiu_em' => 'datetime',
            'tempo_espera_segundos' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $registro) {
            if ($registro->status_saida === null && ! $registro->isForceDeleting()) {
                throw new \LogicException(
                    'ClienteFila: use registrarSaida($status) em vez de delete(). '
                    .'Apagar sem status_saida cria registro fantasma na fila.'
                );
            }
        });
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->whereNull('status_saida');
    }

    /**
     * Única porta de saída da fila. Idempotente.
     * Campos de saída ficam fora do $fillable — a escrita é feita aqui via forceFill.
     */
    public function registrarSaida(string $status): void
    {
        if ($this->status_saida !== null) {
            return;
        }

        $agora = now();

        $this->forceFill([
            'status_saida' => $status,
            'saiu_em' => $agora,
            'tempo_espera_segundos' => (int) abs($this->created_at->diffInSeconds($agora)),
        ])->save();

        $this->delete(); // soft
    }

    /**
     * Saída por chamada para a mesa: grava quando o cliente foi chamado e qual
     * reserva nasceu disso, e fecha a entrada como 'atendido'.
     *
     * Esse 'atendido' é provisório até o check-in da reserva. Se ele não vier
     * dentro da tolerância, a 'fila:expirar-chamados' reclassifica a entrada via
     * registrarNaoComparecimento().
     */
    public function registrarChamada(ClienteMesa $reserva): void
    {
        if ($this->status_saida !== null) {
            return;
        }

        $this->forceFill([
            'chamado_em' => now(),
            'clientemesa_id' => $reserva->id,
        ]);

        $this->registrarSaida(self::STATUS_SAIDA_ATENDIDO);
    }

    /**
     * Foi chamado e não apareceu: 'atendido' vira 'expirado'. Idempotente.
     *
     * Só vale para entrada fechada por registrarChamada() — sem 'chamado_em' não
     * houve chamada, e um 'atendido' sem chamada não é provisório. 'saiu_em' e
     * 'tempo_espera_segundos' ficam como estão: medem a espera até a chamada,
     * que foi real.
     */
    public function registrarNaoComparecimento(): void
    {
        if ($this->status_saida !== self::STATUS_SAIDA_ATENDIDO || $this->chamado_em === null) {
            return;
        }

        $this->forceFill(['status_saida' => self::STATUS_SAIDA_EXPIRADO])->save();
    }

    /**
     * Posição atual na fila, contando apenas clientes ativos.
     * Retorna null para quem já saiu — não existe "posição" de quem não está na fila.
     */
    public function getPosicaoAttribute(): ?int
    {
        if ($this->status_saida !== null || $this->trashed()) {
            return null;
        }

        return 1 + (int) static::query()
            ->ativas()
            ->where('fila_id', $this->fila_id)
            ->where(function (Builder $q) {
                $q->where('created_at', '<', $this->created_at)
                    ->orWhere(function (Builder $q2) {
                        $q2->where('created_at', '=', $this->created_at)
                            ->where('id', '<', $this->id);
                    });
            })
            ->count();
    }

    public function fila(): BelongsTo
    {
        return $this->belongsTo(Fila::class, 'fila_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** Reserva criada quando esta entrada foi chamada para a mesa. */
    public function reservaDaChamada(): BelongsTo
    {
        return $this->belongsTo(ClienteMesa::class, 'clientemesa_id');
    }
}
