<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reserva que nasceu da chamada desta entrada da fila (#12).
     *
     * A promoção cria a reserva e fecha a entrada na mesma transação, mas nada
     * ligava uma à outra. Sem o vínculo, a 'fila:expirar-chamados' não tem como
     * saber se quem foi chamado chegou a fazer check-in — e é isso que separa o
     * atendido de verdade do no-show.
     *
     * nullOnDelete: a exclusão definitiva de uma reserva não pode levar junto o
     * histórico da fila.
     */
    public function up(): void
    {
        Schema::table('clientefila', function (Blueprint $table) {
            $table->uuid('clientemesa_id')->nullable();
            $table->foreign('clientemesa_id')->references('id')->on('clientemesa')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clientefila', function (Blueprint $table) {
            $table->dropForeign(['clientemesa_id']);
            $table->dropColumn('clientemesa_id');
        });
    }
};
