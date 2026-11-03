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
     * @return array<int, EtapaSelecao> Id do candidato => etapa, de todos os vinculados nesta vaga.
     */
    public function etapasPorCandidato(Vaga $vaga): array;

    /** O bloqueio deve ser usado dentro de uma transação. */
    public function encontrar(Vaga $vaga, Candidato $candidato, bool $bloquear = false): ?Participacao;

    /**
     * Cria ou atualiza a participação e grava a movimentação, numa transação. Se o candidato já
     * estiver na etapa pedida, nada é registrado.
     */
    public function moverPara(Vaga $vaga, Candidato $candidato, EtapaSelecao $etapa, User $usuario): Participacao;

    /** @param list<int> $candidatoIds */
    public function adicionarATriagem(Vaga $vaga, array $candidatoIds): void;

    /**
     * Movimentações do candidato nesta vaga, das mais recentes para as mais antigas, com o usuário.
     *
     * @return Collection<int, MovimentacaoEtapa>
     */
    public function historico(Vaga $vaga, Candidato $candidato): Collection;

    /**
     * Candidatos que já saíram da triagem nesta vaga, inclusive desativados, com as competências.
     *
     * @return Collection<int, Candidato>
     */
    public function candidatosRevelados(Vaga $vaga): Collection;
}
