<?php

namespace Database\Factories;

use App\Models\Anotacao;
use App\Models\Candidato;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Anotacao>
 */
class AnotacaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidato_id' => Candidato::factory(),
            'vaga_id' => null,
            'user_id' => User::factory(),
            'texto' => fake()->paragraph(),
        ];
    }
}
