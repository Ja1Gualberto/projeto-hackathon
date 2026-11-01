<?php

namespace App\Services;

use App\DTOs\ItemRankingDTO;
use App\Enums\Area;
use App\Enums\TipoCandidatoVaga;
use App\Models\Competencia;
use App\Models\User;
use App\Models\Vaga;
use App\Repositories\Interfaces\CompetenciaRepositoryInterface;
use App\Repositories\Interfaces\VagaRepositoryInterface;
use App\Services\Aderencia\RankingVagaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro, edição, encerramento e leitura do quadro das vagas (US3, FR-015/FR-016).
 */
class VagaService
{
    public const MENSAGEM_VAGA_ENCERRADA = 'Vaga encerrada — somente consulta. Ela não pode mais ser editada.';

    public function __construct(
        private VagaRepositoryInterface $vagas,
        private CompetenciaRepositoryInterface $competencias,
        private CandidatoService $candidatos,
        private RankingVagaService $ranking,
    ) {}

    /**
     * Vagas e a quantidade de candidatos elegíveis de cada uma (ativos do tipo aceito).
     *
     * @return array{vagas: Collection<int, Vaga>, elegiveis: array<int, int>}
     */
    public function listar(): array
    {
        $vagas = $this->vagas->listar();
        $totais = $this->candidatos->contarAtivosPorTipo();

        return [
            'vagas' => $vagas,
            'elegiveis' => $vagas->mapWithKeys(fn (Vaga $vaga): array => [
                $vaga->id => match ($vaga->tipo_candidato) {
                    TipoCandidatoVaga::Servidor => $totais['servidores'],
                    TipoCandidatoVaga::Estagiario => $totais['estagiarios'],
                    TipoCandidatoVaga::Ambos => array_sum($totais),
                },
            ])->all(),
        ];
    }

    /** @return array{hard: Collection<int, Competencia>, soft: Collection<int, Competencia>} */
    public function catalogo(): array
    {
        return $this->competencias->todasAgrupadasPorCategoria();
    }

    /**
     * @return array<int, int> Id da competência => peso atual na vaga.
     */
    public function pesosAtuais(Vaga $vaga): array
    {
        return $this->vagas->requisitos($vaga)
            ->mapWithKeys(fn (Competencia $competencia): array => [$competencia->id => (int) $competencia->pivot->peso])
            ->all();
    }

    /**
     * @param  array{titulo: string, area: Area, tipo_candidato: TipoCandidatoVaga, descricao: ?string}  $dados
     * @param  array<int, int>  $competenciasComPeso
     */
    public function criar(array $dados, array $competenciasComPeso, User $criador): Vaga
    {
        return DB::transaction(fn (): Vaga => $this->vagas->criar($dados, $competenciasComPeso, $criador));
    }

    /**
     * Vaga encerrada fica somente para consulta: a edição é recusada com erro de validação.
     *
     * @param  array{titulo: string, area: Area, tipo_candidato: TipoCandidatoVaga, descricao: ?string}  $dados
     * @param  array<int, int>  $competenciasComPeso
     *
     * @throws ValidationException
     */
    public function atualizar(Vaga $vaga, array $dados, array $competenciasComPeso): Vaga
    {
        $this->garantirAberta($vaga);

        return DB::transaction(fn (): Vaga => $this->vagas->atualizar($vaga, $dados, $competenciasComPeso));
    }

    /**
     * @throws ValidationException
     */
    public function garantirAberta(Vaga $vaga): void
    {
        if (! $vaga->estaAberta()) {
            throw ValidationException::withMessages(['vaga' => self::MENSAGEM_VAGA_ENCERRADA]);
        }
    }

    public function encerrar(Vaga $vaga): void
    {
        $this->vagas->encerrar($vaga);
    }

    /**
     * Dados da região `quadro-vaga`: a vaga, as competências pedidas com peso e o ranking.
     *
     * @return array{vaga: Vaga, requisitos: Collection<int, Competencia>, ranking: SupportCollection<int, ItemRankingDTO>}
     */
    public function quadro(Vaga $vaga): array
    {
        return [
            'vaga' => $vaga,
            'requisitos' => $this->vagas->requisitos($vaga),
            'ranking' => $this->ranking->ranquear($vaga),
        ];
    }
}
