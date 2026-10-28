<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Momento em que o cliente foi chamado para a mesa.
     *
     * Completa o histórico aberto pela 2026_08_12_175616: 'created_at' marca a
     * entrada e 'saiu_em' a saída, mas a chamada — o instante entre as duas —
     * não era registrada em lugar nenhum. Sem ela não há como distinguir quem
     * desistiu na fila de quem foi chamado e não apareceu.
     *
     * Nasce sem escritor: quem preenche é a 'fila:expirar-chamados' (#12), que
     * compara 'chamado_em' com a janela de tolerância para marcar o no-show
     * como status_saida 'expirado'.
     */
    public function up(): void
    {
        Schema::table('clientefila', function (Blueprint $table) {
            $table->timestamp('chamado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clientefila', function (Blueprint $table) {
            $table->dropColumn('chamado_em');
        });
    }
};
