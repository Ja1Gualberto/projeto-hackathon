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
        Schema::create('competencias', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 80)->unique();
            $table->string('slug', 90)->unique();
            $table->string('categoria')->index();
            $table->string('area')->nullable();
            $table->json('sinonimos');
            $table->string('grupo', 40)->nullable();
            $table->unsignedTinyInteger('nivel')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competencias');
    }
};
