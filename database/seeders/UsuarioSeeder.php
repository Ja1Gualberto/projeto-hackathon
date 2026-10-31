<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Recrutador da demonstração. Credenciais fictícias, documentadas no quickstart.
 */
class UsuarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'recrutador@setic.demo'],
            [
                'name' => 'Recrutadora Demo',
                'password' => 'demo1234',
            ],
        );
    }
}
