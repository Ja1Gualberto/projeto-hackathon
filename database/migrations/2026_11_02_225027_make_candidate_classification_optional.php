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
        Schema::table('candidatos', function (Blueprint $table): void {
            $table->string('tipo')->nullable()->change();
        });

        Schema::table('vagas', function (Blueprint $table): void {
            $table->string('tipo_candidato')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('A classificação antiga não pode ser reconstruída para os novos cadastros.');
    }
};
