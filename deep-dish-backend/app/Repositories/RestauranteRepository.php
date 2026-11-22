<?php

namespace App\Repositories;

use App\Models\Restaurante;

class RestauranteRepository
{
    public function create(array $data): Restaurante
    {
        // ✅ Único lugar que "fala com o banco" nesta arquitetura
        return Restaurante::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'cnpj' => $data['cnpj'],
            'tipo' => $data['tipo'],
            'logradouro' => $data['logradouro'],
            'numero' => $data['numero'],
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'],
            'cidade' => $data['cidade'],
            'estado' => strtoupper($data['estado']),
            'cep' => $data['cep'],
            'telefone' => $data['telefone'] ?? null,
            'imagem_url' => $data['imagem_url'] ?? null,
            'horario_funcionamento' => $data['horario_funcionamento'] ?? null,
            'fila_ativa' => $data['fila_ativa'] ?? false,
            'tipo_usuario' => $data['tipo_usuario'],
            'password' => $data['password'],
        ]);
    }

    /**
     * Busca um restaurante pelo ID.
     */
    public function findById(string $id): ?Restaurante
    {
        return Restaurante::find($id);
    }

    /**
     * Busca restaurantes por nome e endereço usando filtros opcionais.
     *
     * Filtros aceitos:
     * - q: texto livre (nome + logradouro + bairro + cidade + estado + cep)
     * - cidade
     * - estado
     * - bairro
     * - cep
     * - tipo
     *
     * Retorna paginação (10 por página).
     *
     * @param  array<string, mixed>  $filters
     */
    public function searchByEndereco(array $filters)
    {
        $query = Restaurante::query();

        if (! empty($filters['q'])) {
            $term = $filters['q'];

            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('logradouro', 'LIKE', "%{$term}%")
                    ->orWhere('bairro', 'LIKE', "%{$term}%")
                    ->orWhere('cidade', 'LIKE', "%{$term}%")
                    ->orWhere('estado', 'LIKE', "%{$term}%")
                    ->orWhere('cep', 'LIKE', "%{$term}%");
            });
        }

        if (! empty($filters['cidade'])) {
            $query->where('cidade', 'LIKE', '%'.$filters['cidade'].'%');
        }

        if (! empty($filters['estado'])) {
            $query->where('estado', strtoupper($filters['estado']));
        }

        if (! empty($filters['bairro'])) {
            $query->where('bairro', 'LIKE', '%'.$filters['bairro'].'%');
        }

        if (! empty($filters['cep'])) {
            $query->where('cep', 'LIKE', '%'.$filters['cep'].'%');
        }

        if (! empty($filters['tipo'])) {
            $query->whereRaw('LOWER(tipo) LIKE ?', ['%'.strtolower($filters['tipo']).'%']);
        }

        return $query
            ->orderBy('name')
            ->paginate(10);
    }
}
