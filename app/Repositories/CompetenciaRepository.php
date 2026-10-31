<?php

namespace App\Repositories;

use App\Enums\CategoriaCompetencia;
use App\Models\Competencia;
use App\Repositories\Interfaces\CompetenciaRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CompetenciaRepository implements CompetenciaRepositoryInterface
{
    public function todasAgrupadasPorCategoria(): array
    {
        $competencias = Competencia::query()->orderBy('nome')->get();

        return [
            'hard' => $competencias->where('categoria', CategoriaCompetencia::Hard)->values(),
            'soft' => $competencias->where('categoria', CategoriaCompetencia::Soft)->values(),
        ];
    }

    public function idsQueAtendem(int $competenciaId): array
    {
        $competencia = Competencia::query()->findOrFail($competenciaId);

        if (! $competencia->ehIdioma()) {
            return [$competenciaId];
        }

        return Competencia::query()->where('grupo', $competencia->grupo)
            ->where('nivel', '>=', $competencia->nivel)->pluck('id')->all();
    }

    public function buscarPorIds(array $ids): Collection
    {
        return Competencia::query()->whereIn('id', $ids)->orderBy('nome')->get();
    }
}
