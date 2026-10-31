<?php

namespace Database\Factories;

use App\Enums\Area;
use App\Enums\CategoriaCompetencia;
use App\Models\Competencia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Competencia>
 */
class CompetenciaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => Str::title(fake()->unique()->words(2, true)),
            'categoria' => CategoriaCompetencia::Hard,
            'area' => fake()->randomElement(Area::cases()),
            'sinonimos' => [],
            'grupo' => null,
            'nivel' => null,
        ];
    }

    public function hard(): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => CategoriaCompetencia::Hard,
        ]);
    }

    public function soft(): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => CategoriaCompetencia::Soft,
            'area' => null,
        ]);
    }

    public function idioma(string $grupo, int $nivel): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria' => CategoriaCompetencia::Hard,
            'area' => null,
            'grupo' => $grupo,
            'nivel' => $nivel,
        ]);
    }
}
