<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca a procedência do dado de fila e reserva.
     *
     * NULL = dado real, produzido pelo uso da aplicação. Qualquer outro valor
     * identifica geração artificial — hoje só 'sintetico', do
     * HistoricoSinteticoSeeder. Existe para que um gráfico da entrega possa
     * declarar o que está plotando, e para que a regeração apague exatamente o
     * que ela mesma criou.
     *
     * A coluna é aditiva e inerte: fica fora do $fillable dos models e nenhum
     * código de produção lê ou escreve nela. Não antecipa nem conflita com o
     * refactor de ClienteMesa, que é assunto de outra sprint.
     *
     * 'fila' entra junto porque a fila é criada pelo seeder tanto quanto suas
     * entradas. Sem marcá-la, cada regeração deixaria filas órfãs acumulando —
     * e o critério de "rodar duas vezes não duplica" cairia.
     */
    public function up(): void
    {
        foreach (['fila', 'clientefila', 'clientemesa'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                $table->string('origem')->nullable()->index("{$tabela}_origem_index");
            });
        }
    }

    public function down(): void
    {
        foreach (['fila', 'clientefila', 'clientemesa'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                $table->dropIndex("{$tabela}_origem_index");
                $table->dropColumn('origem');
            });
        }
    }
};
