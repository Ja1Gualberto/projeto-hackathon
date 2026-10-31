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
        Schema::create('participacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vaga_id')->constrained('vagas')->cascadeOnDelete();
            $table->foreignId('candidato_id')->constrained('candidatos')->cascadeOnDelete();
            $table->string('etapa');
            $table->timestamps();

            $table->unique(['vaga_id', 'candidato_id']);
            $table->index(['vaga_id', 'etapa']);
        });

        Schema::create('movimentacoes_etapa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participacao_id')->constrained('participacoes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('de');
            $table->string('para');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimentacoes_etapa');
        Schema::dropIfExists('participacoes');
    }
};
