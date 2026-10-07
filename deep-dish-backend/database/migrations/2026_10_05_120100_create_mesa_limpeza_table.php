<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histórico do ciclo de limpeza da mesa (#171).
     *
     * O intervalo entre "cliente saiu" e "mesa pronta" é onde o tempo se perde, e
     * hoje ele é invisível: 'liberar' devolvia a mesa para 'livre' no mesmo
     * instante. Uma coluna em 'mesa' guardaria só o ciclo mais recente, e a métrica
     * do Analytics precisa de média por período — logo, tabela.
     *
     * São dois tempos distintos, e separá-los é o ponto:
     *
     *   aguardando_desde -> iniciada_em    mesa suja e parada (ninguém começou)
     *   iniciada_em      -> finalizada_em  a limpeza em si
     *
     * O primeiro costuma ser o maior, e é o que o restaurante pode atacar.
     *
     * 'liberada_automaticamente' marca o ciclo que a mesas:liberar-limpeza fechou
     * por tolerância vencida. Esses ficam FORA da média: mediriam o esquecimento da
     * equipe, não o tempo de limpeza. Contá-los à parte também responde com que
     * frequência o fluxo é ignorado.
     *
     * Ciclo em aberto = linha com finalizada_em nulo.
     *
     * onDelete cascade: o histórico de limpeza não sobrevive à mesa — diferente do
     * histórico de fila, não há nada a apurar depois que a mesa deixa de existir.
     */
    public function up(): void
    {
        Schema::create('mesa_limpeza', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('mesa_id');
            $table->timestamp('aguardando_desde');
            $table->timestamp('iniciada_em')->nullable();
            $table->timestamp('finalizada_em')->nullable();
            $table->integer('duracao_segundos')->nullable();
            $table->boolean('liberada_automaticamente')->default(false);
            $table->timestamps();

            $table->foreign('mesa_id')->references('id')->on('mesa')->onDelete('cascade');

            // Achar o ciclo em aberto de uma mesa é a consulta mais frequente.
            $table->index(['mesa_id', 'finalizada_em'], 'mesa_limpeza_mesa_aberto_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesa_limpeza');
    }
};
