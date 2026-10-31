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
        Schema::create('perfis_energia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidato_id')->unique()->constrained('candidatos')->cascadeOnDelete();
            $table->unsignedTinyInteger('dinamico_analitico');
            $table->unsignedTinyInteger('individual_equipe');
            $table->unsignedTinyInteger('execucao_planejamento');
            $table->text('justificativa');
            $table->string('origem');
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('analisado_em');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perfis_energia');
    }
};
