<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientemesa', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->constrained('cliente')
                ->onDelete('cascade');

            $table->foreignId('mesa_id')
                ->constrained('mesa')
                ->onDelete('cascade');

            $table->timestamp('horario_reserva')->nullable();
            $table->string('status')->default('pendente');

            // evita duplicar o mesmo cliente na mesma mesa
            $table->unique(['cliente_id', 'mesa_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientemesa');
    }
};
