<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->unsignedBigInteger('token_version')->default(0)->after('password');
        });

        Schema::table('restaurante', function (Blueprint $table) {
            $table->unsignedBigInteger('token_version')->default(0)->after('tipo_usuario');
        });
    }

    public function down(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->dropColumn('token_version');
        });

        Schema::table('restaurante', function (Blueprint $table) {
            $table->dropColumn('token_version');
        });
    }
};
