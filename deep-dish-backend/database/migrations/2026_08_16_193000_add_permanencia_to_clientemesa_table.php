<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permanência real do cliente na mesa, para giro de mesa e duração média.
     *
     * 'horario_checkin' (já existente) marca a entrada; 'horario_saida' marca a
     * saída em qualquer um dos quatro encerramentos (liberação, cancelamento,
     * no-show e expiração por fechamento).
     *
     * 'duracao_segundos' fica NULL quando não houve check-in — nunca 0, para não
     * puxar a média de permanência para baixo com quem sequer sentou. A unidade
     * acompanha 'clientefila.tempo_espera_segundos'.
     *
     * Soft delete: 'forceDestroyRestaurante' apaga reservas já finalizadas, que são
     * justamente as que carregam a permanência. Sem isto o painel do restaurante
     * apagaria o histórico que esta migration existe para guardar.
     *
     * O default 'pendente' de 'status' é resíduo do schema de fevereiro: a migration
     * 2026_04_10_100000 converteu as linhas para o vocabulário atual mas não mexeu no
     * default. 'pendente' não pertence a STATUS_ATIVOS nem a STATUS_FINALIZADOS, então
     * uma linha com esse valor fica fora da máquina de estados — não bloqueia a mesa
     * na checagem de conflito, nunca expira e não aceita check-in. Derrubar o default
     * faz um insert sem 'status' falhar alto em vez de criar esse registro fantasma.
     */
    public function up(): void
    {
        Schema::table('clientemesa', function (Blueprint $table) {
            $table->timestamp('horario_saida')->nullable();
            $table->unsignedInteger('duracao_segundos')->nullable();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE clientemesa ALTER COLUMN status DROP DEFAULT');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE clientemesa ALTER COLUMN status SET DEFAULT 'pendente'");

        Schema::table('clientemesa', function (Blueprint $table) {
            $table->dropColumn(['horario_saida', 'duracao_segundos']);
            $table->dropSoftDeletes();
        });
    }
};
