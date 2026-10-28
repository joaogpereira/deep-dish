<?php

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\Restaurante;
use Illuminate\Support\Facades\Broadcast;

/*
 * O usuario que chega aqui pode ter vindo de qualquer um dos dois guards JWT
 * (api => Cliente, restaurante => Restaurante), entao o tipo importa tanto quanto
 * o id: sem o instanceof, bastaria um id coincidente para um cliente assinar o
 * canal da operacao de um restaurante.
 */

// Operacao do restaurante: fila e, nas fases seguintes, status de mesa.
Broadcast::channel('restaurante.{restauranteId}', function ($user, string $restauranteId) {
    return $user instanceof Restaurante && (string) $user->id === $restauranteId;
});

// Canal pessoal do cliente: promocao para mesa e, na fase 3, reservas.
Broadcast::channel('cliente.{clienteId}', function ($user, string $clienteId) {
    return $user instanceof Cliente && (string) $user->id === $clienteId;
});

// Canal de uma fila: so quem esta ativo nela assina. O aviso e coletivo ("a fila
// mudou"), entao a posicao de cada um continua sendo consultada individualmente.
Broadcast::channel('fila.{filaId}', function ($user, string $filaId) {
    return $user instanceof Cliente
        && ClienteFila::query()
            ->ativas()
            ->where('fila_id', $filaId)
            ->where('cliente_id', $user->id)
            ->exists();
});
