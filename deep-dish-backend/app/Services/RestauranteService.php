<?php

namespace App\Services;

use App\Models\Restaurante;
use App\Repositories\RestauranteRepository;
use Illuminate\Support\Facades\DB;

class RestauranteService
{
    public function __construct(private RestauranteRepository $repo) {}

    public function register(array $validated): array
    {
        return DB::transaction(function () use ($validated) {
            $validated['tipo_usuario'] = 'restaurante';
            $restaurante = $this->repo->create($validated);
            $token = auth('restaurante')->login($restaurante);

            $restaurante->sendEmailVerificationNotification();

            return [
                'restaurante' => $restaurante,
                'token' => $token,
            ];
        });
    }

    /**
     * Busca um restaurante pelo ID.
     */
    public function findById(string $id): ?Restaurante
    {
        return $this->repo->findById($id);
    }

    /**
     * Busca restaurantes por nome e campos de endereço.
     *
     * @param  array<string, mixed>  $filters
     */
    public function searchByEndereco(array $filters)
    {
        return $this->repo->searchByEndereco($filters);
    }
}
