<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Registro mínimo de exclusão definitiva (LGPD): nenhuma coluna guarda dado pessoal,
     * código ou id do candidato excluído.
     */
    public function up(): void
    {
        Schema::create('registros_exclusao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo');
            $table->timestamp('excluido_em');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registros_exclusao');
    }
};
