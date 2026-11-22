<?php

namespace App\Services;

use App\Repositories\ClienteRepository;
use Illuminate\Support\Facades\DB;

class ClienteService
{
    public function __construct(private ClienteRepository $repo) {}

    public function register(array $validated): array
    {
        return DB::transaction(function () use ($validated) {
            $cliente = $this->repo->create($validated);
            $token = auth('api')->login($cliente);

            $cliente->sendEmailVerificationNotification();

            return [
                'cliente' => $cliente,
                'token' => $token,
            ];
        });
    }
}
