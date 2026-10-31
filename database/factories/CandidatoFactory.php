<?php

namespace Database\Factories;

use App\Enums\Area;
use App\Enums\MeioConsentimento;
use App\Enums\StatusCandidato;
use App\Enums\TipoCandidato;
use App\Models\Candidato;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidato>
 */
class CandidatoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => fake()->randomElement(TipoCandidato::cases()),
            'area' => fake()->randomElement(Area::cases()),
            'status' => StatusCandidato::Ativo,
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'telefone' => fake()->numerify('(69) 9####-####'),
            'cpf' => null,
            'data_nascimento' => fake()->dateTimeBetween('-45 years', '-18 years'),
            'formacoes' => [fake()->sentence(6)],
            'experiencias' => [fake()->sentence(8)],
            'experiencia_na_area' => fake()->boolean(),
            'projetos' => [],
            'links' => [],
            'consentimento_meio' => fake()->randomElement(MeioConsentimento::cases()),
            'consentimento_em' => fake()->dateTimeBetween('-6 months', 'now'),
            'dados_atualizados_em' => now(),
        ];
    }

    public function servidor(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => TipoCandidato::Servidor,
        ]);
    }

    public function estagiario(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => TipoCandidato::Estagiario,
        ]);
    }

    public function desativado(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusCandidato::Desativado,
        ]);
    }

    /**
     * Cadastro com a última atualização há mais de 2 anos (revisão de retenção pendente).
     */
    public function retencaoVencida(): static
    {
        return $this->state(fn (array $attributes) => [
            'dados_atualizados_em' => now()->subYears(2)->subMonths(2),
        ]);
    }
}
