<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funcionario', function (Blueprint $table) {
            $table->string('motivo_afastamento')->nullable()->after('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('funcionario', function (Blueprint $table) {
            $table->dropColumn('motivo_afastamento');
        });
    }
};
