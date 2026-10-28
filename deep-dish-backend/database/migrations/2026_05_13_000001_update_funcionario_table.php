<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('funcionario');

        Schema::create('funcionario', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('restaurante_id')->constrained('restaurante')->onDelete('cascade');
            $table->string('name');
            $table->string('cargo');
            $table->string('horario')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funcionario');
    }
};
