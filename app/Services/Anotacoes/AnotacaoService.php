<?php

namespace App\Services\Anotacoes;

use App\Enums\OrigemCompetencia;
use App\Models\Anotacao;
use App\Models\Candidato;
use App\Models\User;
use App\Models\Vaga;
use App\Repositories\Interfaces\AnotacaoRepositoryInterface;
use App\Repositories\Interfaces\CandidatoRepositoryInterface;
use App\Repositories\Interfaces\VagaRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anotações de entrevista (US5, FR-026/FR-027): autor e data automáticos, vaga opcional e
 * competências observadas, que passam a constar no perfil com a origem "observada em entrevista".
 */
class AnotacaoService
{
    public const MENSAGEM_VAGA_ENCERRADA = 'Vaga encerrada — somente consulta. Registre a anotação pela gestão do banco.';

    public function __construct(
        private AnotacaoRepositoryInterface $anotacoes,
        private CandidatoRepositoryInterface $candidatos,
        private VagaRepositoryInterface $vagas,
    ) {}

    /**
     * Cria a anotação e promove cada competência observada para a origem `entrevista` no perfil
     * (a maior prioridade: nunca rebaixa), tudo numa transação.
     *
     * @param  list<int>  $competenciasObservadas
     */
    public function registrar(Candidato $candidato, ?Vaga $vaga, User $autor, string $texto, array $competenciasObservadas): Anotacao
    {
        return DB::transaction(function () use ($candidato, $vaga, $autor, $texto, $competenciasObservadas): Anotacao {
            $anotacao = $this->anotacoes->criar($candidato, $vaga, $autor, $texto, $competenciasObservadas);

            if ($competenciasObservadas !== []) {
                $origens = $this->candidatos->origensDasCompetencias($candidato);

                foreach ($competenciasObservadas as $id) {
                    $atual = $origens[$id] ?? null;

                    if ($atual === null || OrigemCompetencia::Entrevista->prioridade() > $atual->prioridade()) {
                        $origens[$id] = OrigemCompetencia::Entrevista;
                    }
                }

                $this->candidatos->sincronizarCompetencias($candidato, $origens);
            }

            return $anotacao;
        });
    }

    /**
     * Pelo contexto da vaga a anotação fica associada a ela. Vaga encerrada é somente consulta;
     * a anotação continua possível pela gestão do banco.
     *
     * @param  list<int>  $competenciasObservadas
     *
     * @throws ValidationException
     */
    public function registrarNaVaga(Vaga $vaga, Candidato $candidato, User $autor, string $texto, array $competenciasObservadas): Anotacao
    {
        if (! $vaga->estaAberta()) {
            throw ValidationException::withMessages(['texto' => self::MENSAGEM_VAGA_ENCERRADA]);
        }

        return $this->registrar($candidato, $vaga, $autor, $texto, $competenciasObservadas);
    }

    /**
     * @return Collection<int, Anotacao>
     */
    public function historico(Candidato $candidato): Collection
    {
        return $this->anotacoes->historico($candidato);
    }

    /**
     * Vagas que podem ser associadas a uma anotação feita pela gestão do banco.
     *
     * @return Collection<int, Vaga>
     */
    public function vagasParaAssociar(): Collection
    {
        return $this->vagas->listar();
    }

    public function vagaPorId(?int $id): ?Vaga
    {
        return $id === null ? null : $this->vagas->encontrar($id);
    }
}
