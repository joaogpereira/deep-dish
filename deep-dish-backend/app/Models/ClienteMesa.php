<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteMesa extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /** Status que seguram a mesa no horário da reserva. */
    public const STATUS_ATIVOS = ['confirmada', 'em_andamento'];

    /** Duração fixa da reserva (MVP). */
    public const DURACAO_RESERVA_MINUTOS = 60;

    protected $table = 'clientemesa';

    protected $fillable = [
        'cliente_id',
        'mesa_id',
        'horario_reserva',
        'horario_checkin',
        'party_size',
        'status',
    ];

    // 'horario_saida' e 'duracao_segundos' ficam fora do $fillable de propósito:
    // a duração é derivada dos timestamps, então só registrarSaida() pode escrevê-la.

    protected function casts(): array
    {
        return [
            'horario_reserva' => 'datetime',
            'horario_checkin' => 'datetime',
            'horario_saida' => 'datetime',
            'duracao_segundos' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Única porta de saída da mesa. Idempotente.
     *
     * A duração só existe para quem fez check-in: sem 'horario_checkin' o cliente
     * nunca sentou (no-show, ou cancelamento antes de chegar) e o campo fica NULL,
     * não 0 — para não rebaixar a média de permanência do Analytics.
     */
    public function registrarSaida(): void
    {
        if ($this->horario_saida !== null) {
            return;
        }

        $agora = now();

        $this->forceFill([
            'horario_saida' => $agora,
            'duracao_segundos' => $this->horario_checkin
                ? (int) abs($this->horario_checkin->diffInSeconds($agora))
                : null,
        ])->save();
    }

    /**
     * Reservas ativas que ocupam a mesa em algum instante da janela pedida.
     *
     * Duas reservas se sobrepõem quando uma começa antes de a outra terminar,
     * dos dois lados. Como só existe o início no banco, o fim sai do início mais
     * a duração fixa — daí o interval no SQL (sintaxe Postgres).
     *
     * Era a mesma consulta escrita em três lugares (ReservaController::store,
     * duas vezes, e MesaController::disponiveis); agora mora aqui.
     */
    public function scopeAtivasSobrepondo(Builder $query, CarbonInterface $inicio, CarbonInterface $fim): Builder
    {
        return $query
            ->whereIn('status', self::STATUS_ATIVOS)
            ->where('horario_reserva', '<', $fim)
            // A duração é uma constante de classe (int), não entrada de usuário.
            ->whereRaw("horario_reserva + interval '".self::DURACAO_RESERVA_MINUTOS." minutes' > ?", [$inicio]);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class, 'mesa_id');
    }
}
