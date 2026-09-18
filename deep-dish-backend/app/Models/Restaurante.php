<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Restaurante extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasFactory, HasUuids, Notifiable; // adicionado HasUuids

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'restaurante';

    protected $fillable = [
        'name',
        'email',
        'cnpj',
        'tipo',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'estado',
        'cep',
        'telefone',
        'imagem_url',
        'horario_abertura',
        'horario_fechamento',
        'fila_ativa',
        'rating',
        'price_range',
        'reservations_enabled',
        'description',
        'tipo_usuario',
        'password',
        'token_version',
    ];

    protected $appends = ['endereco_completo', 'tamanho_fila_atual'];

    protected $hidden = [
        'password',
        'email',
        'cnpj',
        'token_version',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'token_version' => 'integer',
            'rating' => 'decimal:1',
            'price_range' => 'integer',
            'reservations_enabled' => 'boolean',
        ];
    }

    /**
     * Carrega o tamanho da fila via JOIN, evitando N+1 em listagens.
     * Uso: Restaurante::comTamanhoFila()->get()
     */
    public function scopeComTamanhoFila(Builder $query): Builder
    {
        return $query->withCount([
            'clienteFilas as tamanho_fila_atual' => fn (Builder $q) => $q
                ->ativas()
                ->whereHas('fila', fn (Builder $f) => $f->where('status', Fila::STATUS_ABERTA)),
        ]);
    }

    /**
     * Conta apenas clientes ativos (status_saida IS NULL) em filas abertas.
     * Se o valor já veio de scopeComTamanhoFila(), reaproveita em vez de consultar.
     */
    public function getTamanhoFilaAtualAttribute(): int
    {
        if (array_key_exists('tamanho_fila_atual', $this->attributes)) {
            return (int) $this->attributes['tamanho_fila_atual'];
        }

        return $this->clienteFilas()
            ->ativas()
            ->whereHas('fila', fn (Builder $q) => $q->where('status', Fila::STATUS_ABERTA))
            ->count();
    }

    public function getEnderecoCompletoAttribute(): string
    {
        $partes = array_filter([
            $this->logradouro,
            $this->numero,
            $this->complemento,
            $this->bairro,
            $this->cidade,
            $this->estado,
            $this->cep,
        ]);

        return implode(', ', $partes);
    }

    /**
     * O banco guarda só o caminho no disco ("restaurantes/x.jpg"); a URL sai daqui,
     * pelo disco configurado em FILESYSTEM_DISK. Assim trocar de storage (local →
     * Supabase) não exige migrar dados nem mexer no frontend. URLs absolutas
     * legadas passam direto. Para ler o caminho cru: getRawOriginal('imagem_url').
     */
    public function getImagemUrlAttribute(?string $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        return str_starts_with($valor, 'http') ? $valor : Storage::url($valor);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'token_version' => $this->token_version ?? 0,
        ];
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class, 'restaurante_id');
    }

    public function filas(): HasMany
    {
        return $this->hasMany(Fila::class, 'restaurante_id');
    }

    public function clienteFilas(): HasManyThrough
    {
        return $this->hasManyThrough(
            ClienteFila::class,
            Fila::class,
            'restaurante_id', // FK em fila
            'fila_id',        // FK em clientefila
            'id',             // PK em restaurante
            'id'              // PK em fila
        );
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification('restaurante'));
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token, 'restaurante'));
    }
}
