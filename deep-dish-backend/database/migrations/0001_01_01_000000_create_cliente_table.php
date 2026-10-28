<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Função para criar a migração da tabela de clientes.
     */
    public function up(): void
    {
        // criando tabela dos clientes
        Schema::create('cliente', function (Blueprint $table) {
            // id auto-incrementável
            $table->id();
            // campo de nome do tipo string
            $table->string('name');
            // campo de email do tipo string e único
            $table->string('email')->unique();
            // campo de cpf do tipo string e único
            $table->string('cpf')->unique();
            // campo de senha do tipo string
            $table->string('password');
            // campo de Criado e atualizado do tipo timestamp
            $table->timestamps();
        });
    }

    /**
     * Função para reverter a migração caso seja necessário.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente');
    }
};
