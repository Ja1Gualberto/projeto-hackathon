<?php

namespace App\Repositories\Interfaces;

use App\Enums\CategoriaCompetencia;
use App\Models\Competencia;
use Illuminate\Database\Eloquent\Collection;

interface CompetenciaRepositoryInterface
{
    /** @return array{hard: Collection<int, Competencia>, soft: Collection<int, Competencia>} */
    public function todasAgrupadasPorCategoria(): array;

    /** @return list<int> */
    public function idsQueAtendem(int $competenciaId): array;

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Competencia>
     */
    public function buscarPorIds(array $ids): Collection;

    /** @param array{nome: string, categoria: CategoriaCompetencia, sinonimos: list<string>} $atributos */
    public function criar(array $atributos): Competencia;
}
