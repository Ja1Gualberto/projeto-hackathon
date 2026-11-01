<?php

namespace App\Repositories\Interfaces;

use App\Enums\Area;
use App\Enums\TipoCandidatoVaga;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\User;
use App\Models\Vaga;
use Illuminate\Database\Eloquent\Collection;

interface VagaRepositoryInterface
{
    /**
     * Vagas abertas primeiro e mais recentes antes, com `competencias_count`,
     * `entrevista_count` e `aprovado_count`.
     *
     * @return Collection<int, Vaga>
     */
    public function listar(): Collection;

    /**
     * @param  array{titulo: string, area: Area, tipo_candidato: TipoCandidatoVaga, descricao: ?string}  $dados
     * @param  array<int, int>  $competenciasComPeso  Id da competência => peso (1 a 3).
     */
    public function criar(array $dados, array $competenciasComPeso, User $criador): Vaga;

    /**
     * @param  array{titulo: string, area: Area, tipo_candidato: TipoCandidatoVaga, descricao: ?string}  $dados
     * @param  array<int, int>  $competenciasComPeso  Id da competência => peso; as ausentes saem da vaga.
     */
    public function atualizar(Vaga $vaga, array $dados, array $competenciasComPeso): Vaga;

    /** Encerra uma vaga aberta; numa vaga já encerrada, mantém a data original. */
    public function encerrar(Vaga $vaga): void;

    /**
     * Competências pedidas pela vaga, lidas do banco a cada chamada (FR-020), com `pivot->peso`.
     *
     * @return Collection<int, Competencia>
     */
    public function requisitos(Vaga $vaga): Collection;

    /**
     * Ativos do tipo aceito pela vaga, com as competências, em ordem de cadastro (`created_at`, `id`).
     *
     * @return Collection<int, Candidato>
     */
    public function candidatosElegiveis(Vaga $vaga): Collection;
}
