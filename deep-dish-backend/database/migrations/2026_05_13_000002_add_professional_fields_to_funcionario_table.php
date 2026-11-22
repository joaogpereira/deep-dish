<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funcionario', function (Blueprint $table) {
            $table->string('cpf', 14)->nullable()->after('cargo');
            $table->string('rg', 20)->nullable()->after('cpf');
            $table->string('telefone', 20)->nullable()->after('rg');
            $table->string('email')->nullable()->after('telefone');
            $table->date('data_nascimento')->nullable()->after('email');
            $table->text('observacoes')->nullable()->after('horario');
        });
    }

    public function down(): void
    {
        Schema::table('funcionario', function (Blueprint $table) {
            $table->dropColumn(['cpf', 'rg', 'telefone', 'email', 'data_nascimento', 'observacoes']);
        });
    }
};
