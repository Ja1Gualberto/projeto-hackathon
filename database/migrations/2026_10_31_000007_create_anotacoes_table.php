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
        Schema::create('anotacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidato_id')->constrained('candidatos')->cascadeOnDelete();
            $table->foreignId('vaga_id')->nullable()->constrained('vagas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('texto');
            $table->timestamps();
        });

        Schema::create('anotacao_competencia', function (Blueprint $table) {
            $table->foreignId('anotacao_id')->constrained('anotacoes')->cascadeOnDelete();
            $table->foreignId('competencia_id')->constrained('competencias')->restrictOnDelete();

            $table->primary(['anotacao_id', 'competencia_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anotacao_competencia');
        Schema::dropIfExists('anotacoes');
    }
};
