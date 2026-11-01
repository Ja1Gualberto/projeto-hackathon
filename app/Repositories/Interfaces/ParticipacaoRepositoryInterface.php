<?php

namespace App\Repositories\Interfaces;

use App\Enums\EtapaSelecao;
use App\Models\Candidato;
use App\Models\MovimentacaoEtapa;
use App\Models\Participacao;
use App\Models\User;
use App\Models\Vaga;
use Illuminate\Database\Eloquent\Collection;

interface ParticipacaoRepositoryInterface
{
    /**
     * @return array<int, EtapaSelecao> Id do candidato => etapa, só de quem já foi movido nesta vaga.
     */
    public function etapasPorCandidato(Vaga $vaga): array;

    public function encontrar(Vaga $vaga, Candidato $candidato): ?Participacao;

    /**
     * Cria ou atualiza a participação e grava a movimentação, numa transação. Se o candidato já
     * estiver na etapa pedida, nada é registrado (sem participação, a etapa é a triagem).
     */
    public function moverPara(Vaga $vaga, Candidato $candidato, EtapaSelecao $etapa, User $usuario): Participacao;

    /**
     * Movimentações do candidato nesta vaga, das mais recentes para as mais antigas, com o usuário.
     *
     * @return Collection<int, MovimentacaoEtapa>
     */
    public function historico(Vaga $vaga, Candidato $candidato): Collection;

    /**
     * Candidatos em Entrevista ou Aprovado nesta vaga, inclusive desativados, com as competências.
     *
     * @return Collection<int, Candidato>
     */
    public function candidatosRevelados(Vaga $vaga): Collection;
}
