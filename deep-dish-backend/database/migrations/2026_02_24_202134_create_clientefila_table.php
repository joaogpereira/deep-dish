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
        Schema::create('clientefila', function (Blueprint $table) {
            $table->id();
            $table->ForeignId('fila_id')->constrained('fila')->onDelete('cascade');
            $table->ForeignId('cliente_id')->constrained('cliente')->onDelete('cascade');
            $table->integer('qntd_pessoas')->default(1);
            // O creadet_at vai servir para saber o lugar na fila.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientefila');
    }
};
