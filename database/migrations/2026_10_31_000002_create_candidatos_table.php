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
        Schema::create('candidatos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 9)->unique();
            $table->string('tipo');
            $table->string('area')->index();
            $table->string('status')->default('ativo');
            $table->string('nome', 150);
            $table->string('email', 150)->nullable()->index();
            $table->string('telefone', 30)->nullable();
            $table->char('cpf', 11)->nullable()->index();
            $table->date('data_nascimento')->nullable();
            $table->json('formacoes');
            $table->json('experiencias');
            $table->json('projetos');
            $table->json('links');
            $table->boolean('experiencia_na_area')->default(false);
            $table->longText('texto_curriculo')->nullable();
            $table->string('curriculo_arquivo')->nullable();
            $table->string('curriculo_nome_original')->nullable();
            $table->unsignedInteger('curriculo_tamanho')->nullable();
            $table->string('consentimento_meio');
            $table->date('consentimento_em');
            $table->foreignId('consentimento_registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dados_atualizados_em');
            $table->timestamps();

            $table->index(['status', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidatos');
    }
};
