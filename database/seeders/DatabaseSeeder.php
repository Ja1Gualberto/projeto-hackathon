<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Ordem final da carga de demonstração. Os eventos de model ficam ativos: o código
     * anônimo dos candidatos e o slug das competências são gerados no evento `creating`.
     */
    public function run(): void
    {
        $this->call([
            UsuarioSeeder::class,
            CompetenciaSeeder::class,
            CandidatoSeeder::class,
            VagaSeeder::class,
        ]);
    }
}
