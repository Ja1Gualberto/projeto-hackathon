<?php

namespace App\Services\Energia;

use App\DTOs\SugestaoEnergiaDTO;
use App\Enums\OrigemPerfilEnergia;
use App\Models\Candidato;
use App\Models\PerfilEnergia;
use App\Models\User;
use App\Models\Vaga;
use App\Repositories\Interfaces\CandidatoRepositoryInterface;
use App\Services\Selecao\LimpadorTexto;
use Illuminate\Database\Eloquent\Collection;

class PerfilEnergiaService
{
    public function __construct(
        private AnalisadorEnergia $analisador,
        private CandidatoRepositoryInterface $candidatos,
        private LimpadorTexto $limpador,
        private ProximidadeEnergia $proximidade,
    ) {}

    public function textoAnonimizado(Candidato $candidato): string
    {
        $candidato = $this->candidatos->detalhar($candidato);

        return $this->limpar($candidato, implode("\n", [
            'FORMAÇÃO', ...($candidato->formacoes ?? []),
            'EXPERIÊNCIAS', ...($candidato->experiencias ?? []),
            'PROJETOS', ...($candidato->projetos ?? []),
            'COMPETÊNCIAS', ...$candidato->competencias->pluck('nome')->all(),
        ]));
    }

    public function analisar(Candidato $candidato): ?PerfilEnergia
    {
        $sugestao = $this->analisador->analisar($this->textoAnonimizado($candidato));
        if ($sugestao === null) {
            return null;
        }

        return $this->candidatos->salvarPerfilEnergia($candidato, [
            ...$sugestao->eixos(),
            'justificativa' => mb_substr($this->limpar($candidato, $sugestao->justificativa), 0, 500),
            'origem' => OrigemPerfilEnergia::Ia,
            'revisado_por' => null,
            'analisado_em' => now(),
        ]);
    }

    /** @param array{dinamico_analitico: int, individual_equipe: int, execucao_planejamento: int} $eixos */
    public function ajustar(Candidato $candidato, array $eixos, ?string $justificativa, User $revisor): PerfilEnergia
    {
        return $this->candidatos->salvarPerfilEnergia($candidato, [
            'dinamico_analitico' => $eixos['dinamico_analitico'],
            'individual_equipe' => $eixos['individual_equipe'],
            'execucao_planejamento' => $eixos['execucao_planejamento'],
            'justificativa' => $justificativa ?? '',
            'origem' => OrigemPerfilEnergia::Humano,
            'revisado_por' => $revisor->id,
            'analisado_em' => now(),
        ]);
    }

    /** Perfil seguro para o DTO da vaga, inclusive quando a justificativa foi escrita por humano. */
    public function sugestaoNaVaga(Candidato $candidato): ?SugestaoEnergiaDTO
    {
        $perfil = $candidato->perfilEnergia;

        return $perfil === null ? null : new SugestaoEnergiaDTO(
            $perfil->dinamico_analitico, $perfil->individual_equipe, $perfil->execucao_planejamento,
            $this->limpar($candidato, $perfil->justificativa),
        );
    }

    public function proximidadeNaVaga(Vaga $vaga, Candidato $candidato): ?int
    {
        $perfil = $candidato->perfilEnergia;
        if (! $vaga->temPadraoEnergia() || $perfil === null) {
            return null;
        }

        return $this->proximidade->calcular(
            [(int) $vaga->energia_dinamico_analitico, (int) $vaga->energia_individual_equipe, (int) $vaga->energia_execucao_planejamento],
            [$perfil->dinamico_analitico, $perfil->individual_equipe, $perfil->execucao_planejamento],
        );
    }

    /** @return Collection<int, Candidato> */
    public function candidatosParaAnalise(bool $todos): Collection
    {
        return $this->candidatos->candidatosParaAnaliseEnergia($todos);
    }

    private function limpar(Candidato $candidato, string $texto): string
    {
        $partes = preg_split('/\s+/u', trim($candidato->nome)) ?: [];

        return $this->limpador->limpar($texto, [
            $candidato->nome, ...array_filter($partes, fn (string $parte): bool => mb_strlen($parte) >= 2),
            $candidato->email ?? '', $candidato->telefone ?? '', $candidato->cpf ?? '', $candidato->codigo,
        ]);
    }
}
