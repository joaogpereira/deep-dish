<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conversas do assistente do painel e o consumo de tokens (#179).
     *
     * São três tabelas porque são três perguntas diferentes:
     *
     *   assistente_conversa   de quem é a conversa (o escopo por restaurante)
     *   assistente_mensagem   o que foi dito, na ordem (o contexto)
     *   assistente_uso        quanto custou (o teto de tokens)
     *
     * O uso fica fora das mensagens porque pergunta que FALHA também gasta token
     * e não entra no histórico — se o consumo morasse nas mensagens, a falha
     * sairia de graça no orçamento.
     *
     * 'posicao' existe porque o turno inteiro é gravado na mesma transação: o
     * created_at empata e não serve para ordenar.
     */
    public function up(): void
    {
        Schema::create('assistente_conversa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurante_id');
            $table->timestamps();

            $table->foreign('restaurante_id')->references('id')->on('restaurante')->onDelete('cascade');
            $table->index('restaurante_id');
        });

        Schema::create('assistente_mensagem', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversa_id');
            $table->unsignedInteger('posicao');
            $table->string('role', 16);
            $table->text('content')->nullable();
            $table->json('tool_calls')->nullable();
            $table->string('tool_call_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('conversa_id')->references('id')->on('assistente_conversa')->onDelete('cascade');
            $table->unique(['conversa_id', 'posicao']);
        });

        Schema::create('assistente_uso', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurante_id');
            $table->uuid('conversa_id')->nullable();
            $table->unsignedInteger('tokens_prompt');
            $table->unsignedInteger('tokens_resposta');
            $table->unsignedInteger('tokens_total');
            $table->boolean('ok');
            $table->timestamp('created_at')->nullable();

            $table->foreign('restaurante_id')->references('id')->on('restaurante')->onDelete('cascade');
            $table->foreign('conversa_id')->references('id')->on('assistente_conversa')->nullOnDelete();

            // A soma do período é feita antes de toda pergunta.
            $table->index(['restaurante_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistente_uso');
        Schema::dropIfExists('assistente_mensagem');
        Schema::dropIfExists('assistente_conversa');
    }
};
