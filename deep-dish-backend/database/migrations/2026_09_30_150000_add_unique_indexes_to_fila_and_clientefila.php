<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Transforma em garantia do banco duas regras que só existiam em PHP.
 *
 * O FilaService já tinha `catch (QueryException)` esperando estas violações —
 * mas nenhuma migration criava os índices, então as redes de segurança nunca
 * seriam acionadas. A checagem "esse cliente já está na fila?" seguida de um
 * insert, mesmo dentro de transação, não segura duas requisições simultâneas:
 * em READ COMMITTED as duas leem "não está" e as duas inserem. Em
 * desenvolvimento isso não aparece, porque o 'artisan serve' atende uma
 * requisição por vez; com php-fpm em produção, aparece.
 *
 * São índices PARCIAIS de propósito:
 * - fila: só as abertas. Filas encerradas da mesma janela podem coexistir no
 *   histórico, que é o que o Analytics lê.
 * - clientefila: só quem está esperando. Quem saiu (desistiu, foi atendido,
 *   removido ou expirou) pode entrar de novo na mesma fila — e o teste
 *   'cliente que saiu pode reentrar na fila' cobre exatamente isso.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE UNIQUE INDEX fila_aberta_unica
            ON fila (restaurante_id, horario_reserva)
            WHERE status = \'aberta\'
        ');

        DB::statement('
            CREATE UNIQUE INDEX clientefila_ativa_unica
            ON clientefila (fila_id, cliente_id)
            WHERE status_saida IS NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS clientefila_ativa_unica');
        DB::statement('DROP INDEX IF EXISTS fila_aberta_unica');
    }
};
