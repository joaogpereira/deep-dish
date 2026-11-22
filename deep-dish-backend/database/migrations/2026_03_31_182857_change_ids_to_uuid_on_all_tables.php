<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite não suporta dropColumn em PKs
        // então recriamos as tabelas do zero na ordem correta

        // 1. Apaga na ordem inversa (filhos antes dos pais)
        Schema::dropIfExists('clientemesa');
        Schema::dropIfExists('clientefila');
        Schema::dropIfExists('fila');
        Schema::dropIfExists('funcionario');
        Schema::dropIfExists('mesa');
        Schema::dropIfExists('restaurante');
        Schema::dropIfExists('cliente');

        // 2. Recria com UUID na ordem correta (pais antes dos filhos)
        Schema::create('cliente', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('cpf')->unique();
            $table->string('password');
            $table->unsignedBigInteger('token_version')->default(0);
            $table->timestamps();
        });

        Schema::create('restaurante', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('cnpj')->unique();
            $table->string('tipo');
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('telefone')->nullable();
            $table->string('imagem_url')->nullable();
            $table->string('horario_funcionamento')->nullable();
            $table->boolean('fila_ativa')->default(false);
            $table->string('password');
            $table->string('tipo_usuario');
            $table->unsignedBigInteger('token_version')->default(0);
            $table->timestamps();
        });

        Schema::create('mesa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurante_id');
            $table->foreign('restaurante_id')->references('id')->on('restaurante')->onDelete('cascade');
            $table->string('status')->default('disponível');
            $table->integer('capacidade')->default(2);
            $table->string('confirmacao')->default('pendente');
            $table->timestamps();
        });

        Schema::create('funcionario', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurante_id');
            $table->foreign('restaurante_id')->references('id')->on('restaurante')->onDelete('cascade');
            $table->string('name');
            $table->string('presenca')->default('ausente');
            $table->timestamps();
        });

        Schema::create('fila', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('restaurante_id');
            $table->foreign('restaurante_id')->references('id')->on('restaurante')->onDelete('cascade');
            $table->string('status')->default('pendente');
            $table->timestamps();
        });

        Schema::create('clientefila', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fila_id');
            $table->uuid('cliente_id');
            $table->foreign('fila_id')->references('id')->on('fila')->onDelete('cascade');
            $table->foreign('cliente_id')->references('id')->on('cliente')->onDelete('cascade');
            $table->integer('qntd_pessoas')->default(1);
            $table->timestamps();
        });

        Schema::create('clientemesa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cliente_id');
            $table->uuid('mesa_id');
            $table->foreign('cliente_id')->references('id')->on('cliente')->onDelete('cascade');
            $table->foreign('mesa_id')->references('id')->on('mesa')->onDelete('cascade');
            $table->timestamp('horario_reserva')->nullable();
            $table->string('status')->default('pendente');
            $table->unique(['cliente_id', 'mesa_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientemesa');
        Schema::dropIfExists('clientefila');
        Schema::dropIfExists('fila');
        Schema::dropIfExists('funcionario');
        Schema::dropIfExists('mesa');
        Schema::dropIfExists('restaurante');
        Schema::dropIfExists('cliente');
    }
};
