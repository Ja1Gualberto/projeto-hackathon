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
        Schema::create('vagas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 120);
            $table->string('area');
            $table->string('tipo_candidato');
            $table->text('descricao')->nullable();
            $table->string('status')->default('aberta')->index();
            $table->unsignedTinyInteger('energia_dinamico_analitico')->nullable();
            $table->unsignedTinyInteger('energia_individual_equipe')->nullable();
            $table->unsignedTinyInteger('energia_execucao_planejamento')->nullable();
            $table->foreignId('criada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('encerrada_em')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vagas');
    }
};
