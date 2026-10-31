<?php

namespace Database\Factories;

use App\Enums\Area;
use App\Enums\StatusVaga;
use App\Enums\TipoCandidatoVaga;
use App\Models\Vaga;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vaga>
 */
class VagaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => fake()->jobTitle(),
            'area' => fake()->randomElement(Area::cases()),
            'tipo_candidato' => fake()->randomElement(TipoCandidatoVaga::cases()),
            'descricao' => fake()->sentence(12),
            'status' => StatusVaga::Aberta,
            'energia_dinamico_analitico' => null,
            'energia_individual_equipe' => null,
            'energia_execucao_planejamento' => null,
            'encerrada_em' => null,
        ];
    }

    public function encerrada(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusVaga::Encerrada,
            'encerrada_em' => now(),
        ]);
    }

    public function paraEstagiarios(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_candidato' => TipoCandidatoVaga::Estagiario,
        ]);
    }

    public function paraServidores(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_candidato' => TipoCandidatoVaga::Servidor,
        ]);
    }
}
