<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientefila', function (Blueprint $table) {
            $table->string('status_saida')->nullable();
            $table->timestamp('saiu_em')->nullable();
            $table->unsignedInteger('tempo_espera_segundos')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('clientefila', function (Blueprint $table) {
            $table->dropColumn([
                'status_saida',
                'saiu_em',
                'tempo_espera_segundos',
            ]);

            $table->dropSoftDeletes();
        });
    }
};
