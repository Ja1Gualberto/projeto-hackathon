<?php

namespace App\Repositories\Interfaces;

use App\Models\Anotacao;
use App\Models\Candidato;
use App\Models\User;
use App\Models\Vaga;
use Illuminate\Database\Eloquent\Collection;

interface AnotacaoRepositoryInterface
{
    /**
     * Grava a anotação e as competências observadas (pivot `anotacao_competencia`).
     *
     * @param  list<int>  $competenciasObservadas
     */
    public function criar(Candidato $candidato, ?Vaga $vaga, User $autor, string $texto, array $competenciasObservadas): Anotacao;

    /**
     * Todas as anotações do candidato, de qualquer vaga, das mais recentes para as mais antigas,
     * com autor, vaga e competências observadas.
     *
     * @return Collection<int, Anotacao>
     */
    public function historico(Candidato $candidato): Collection;
}
