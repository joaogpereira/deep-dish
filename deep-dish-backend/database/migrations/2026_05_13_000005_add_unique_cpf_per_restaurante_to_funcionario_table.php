<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funcionario', function (Blueprint $table) {
            // NULL values are not considered duplicates in PostgreSQL,
            // so employees without CPF are unaffected.
            $table->unique(['restaurante_id', 'cpf'], 'funcionario_restaurante_cpf_unique');
        });
    }

    public function down(): void
    {
        Schema::table('funcionario', function (Blueprint $table) {
            $table->dropUnique('funcionario_restaurante_cpf_unique');
        });
    }
};
