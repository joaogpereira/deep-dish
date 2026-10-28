<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Funcionario extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'funcionario';

    protected $fillable = [
        'restaurante_id',
        'name',
        'cargo',
        'cpf',
        'telefone',
        'email',
        'data_nascimento',
        'horario',
        'observacoes',
        'ativo',
        'motivo_afastamento',
    ];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'ativo' => 'boolean',
        ];
    }

    public function restaurante(): BelongsTo
    {
        return $this->belongsTo(Restaurante::class, 'restaurante_id');
    }
}
