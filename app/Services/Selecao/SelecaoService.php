<?php

namespace App\Services\Selecao;

use App\DTOs\ItemRankingDTO;
use App\Enums\EtapaSelecao;
use App\Enums\StatusCandidato;
use App\Models\Candidato;
use App\Models\User;
use App\Models\Vaga;
use App\Repositories\Interfaces\ParticipacaoRepositoryInterface;
use App\Repositories\Interfaces\VagaRepositoryInterface;
use App\Services\Aderencia\RankingVagaService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Quadro da vaga e as movimentações entre etapas.
 */
class SelecaoService
{
    public const MENSAGEM_VAGA_ENCERRADA = 'Vaga encerrada — somente consulta. Não é possível mover candidatos.';

    public const MENSAGEM_DESATIVADO = 'Este candidato está desativado e não pode ser movido.';

    public const MENSAGEM_INELEGIVEL = 'Este candidato não é elegível para esta vaga.';

    public function __construct(
        private RankingVagaService $ranking,
        private ParticipacaoRepositoryInterface $participacoes,
        private VagaRepositoryInterface $vagas,
        private VisibilidadeCandidatoService $visibilidade,
    ) {}

    /**
     * Triagem = candidatos vinculados em triagem. As demais etapas incluem quem foi
     * movido e depois desativado (esmaecido, sem ações). Cada coluna segue a ordem do ranking, e o
     * nome só é preenchido nas etapas que revelam a identidade.
     *
     * @return array{triagem: Collection<int, ItemRankingDTO>, entrevista: Collection<int, ItemRankingDTO>, aprovado: Collection<int, ItemRankingDTO>, reprovado: Collection<int, ItemRankingDTO>}
     */
    public function quadro(Vaga $vaga): array
    {
        $etapas = $this->participacoes->etapasPorCandidato($vaga);
        $elegiveis = $this->vagas->candidatosElegiveis($vaga);
        $idsElegiveis = array_flip($elegiveis->modelKeys());
        $candidatos = $elegiveis->concat($this->participacoes->candidatosRevelados($vaga))->unique('id');

        $colunas = ['triagem' => collect(), 'entrevista' => collect(), 'aprovado' => collect(), 'reprovado' => collect()];

        foreach ($this->ranking->classificar($vaga, $candidatos) as ['candidato' => $candidato, 'resultado' => $resultado, 'proximidadeEnergia' => $proximidade]) {
            $etapa = $etapas[$candidato->id] ?? EtapaSelecao::Triagem;

            $colunas[$etapa->value]->push(new ItemRankingDTO(
                codigo: $candidato->codigo,
                area: $candidato->area,
                experienciaNaArea: $candidato->experiencia_na_area,
                resultado: $resultado,
                etapa: $etapa,
                nome: $etapa->revelaIdentidade() ? $candidato->nome : null,
                proximidadeEnergia: $proximidade,
                desativado: $candidato->status === StatusCandidato::Desativado,
                elegivel: isset($idsElegiveis[$candidato->id]),
            ));
        }

        return $colunas;
    }

    /**
     * Toda movimentação é humana e fica registrada. Vaga encerrada, candidato desativado ou sem
     * vínculo recusam a ação com uma mensagem que não identifica o candidato.
     *
     * @throws ValidationException
     */
    public function moverEtapa(Vaga $vaga, Candidato $candidato, EtapaSelecao $etapa, User $usuario): void
    {
        $erro = match (true) {
            ! $vaga->estaAberta() => self::MENSAGEM_VAGA_ENCERRADA,
            $candidato->status === StatusCandidato::Desativado => self::MENSAGEM_DESATIVADO,
            ! $this->visibilidade->elegivel($vaga, $candidato) => self::MENSAGEM_INELEGIVEL,
            default => null,
        };

        if ($erro !== null) {
            throw ValidationException::withMessages(['etapa' => $erro]);
        }

        $this->participacoes->moverPara($vaga, $candidato, $etapa, $usuario);
    }

    /** @param list<int> $candidatoIds */
    public function adicionarATriagem(Vaga $vaga, array $candidatoIds): void
    {
        if ($candidatoIds === []) {
            throw ValidationException::withMessages(['candidatos' => 'Selecione ao menos um candidato para adicionar à triagem.']);
        }

        $this->participacoes->adicionarATriagem($vaga, $candidatoIds);
    }

    /**
     * Mensagem de sucesso no contexto da vaga: nome nas etapas reveladas, código na triagem.
     */
    public function mensagemDeMovimentacao(Candidato $candidato, EtapaSelecao $etapa): string
    {
        $identificacao = $etapa->revelaIdentidade() ? $candidato->nome : $candidato->codigo;

        return "{$identificacao} está agora em {$etapa->rotulo()}.";
    }
}
