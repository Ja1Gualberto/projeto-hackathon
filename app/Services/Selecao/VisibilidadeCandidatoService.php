<?php

namespace App\Services\Selecao;

use App\DTOs\PerfilNaVagaDTO;
use App\Enums\EtapaSelecao;
use App\Enums\StatusCandidato;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Vaga;
use App\Repositories\Interfaces\AnotacaoRepositoryInterface;
use App\Repositories\Interfaces\ParticipacaoRepositoryInterface;
use App\Repositories\Interfaces\VagaRepositoryInterface;
use App\Services\Aderencia\CalculadoraAderencia;
use App\Services\CandidatoService;
use App\Services\Energia\PerfilEnergiaService;
use Illuminate\Database\Eloquent\Collection;

/**
 * O que o recrutador pode ver de um candidato dentro de uma vaga (contracts/visibilidade-por-contexto.md):
 * a identidade só aparece após a triagem, e apenas na vaga em questão.
 */
class VisibilidadeCandidatoService
{
    /** Partículas de nome com três letras ou mais que não identificam ninguém sozinhas. */
    private const PARTICULAS = ['das', 'dos', 'del'];

    public function __construct(
        private ParticipacaoRepositoryInterface $participacoes,
        private VagaRepositoryInterface $vagas,
        private AnotacaoRepositoryInterface $anotacoes,
        private CandidatoService $candidatos,
        private CalculadoraAderencia $calculadora,
        private LimpadorTexto $limpador,
        private PerfilEnergiaService $energia,
    ) {}

    /**
     * Dados da tela do perfil na vaga (página e fragmento `perfil-na-vaga`). O catálogo só vai junto
     * quando o formulário de anotação aparece: identidade revelada e vaga aberta.
     *
     * @return array{vaga: Vaga, perfil: PerfilNaVagaDTO, catalogo: array{hard: Collection<int, Competencia>, soft: Collection<int, Competencia>}|null}
     */
    public function telaDoPerfil(Vaga $vaga, Candidato $candidato): array
    {
        $perfil = $this->perfilNaVaga($vaga, $candidato);

        return [
            'vaga' => $vaga,
            'perfil' => $perfil,
            'catalogo' => $perfil->identidadeRevelada && $vaga->estaAberta() ? $this->candidatos->catalogoCompetencias() : null,
        ];
    }

    /**
     * Sem participação registrada, o candidato elegível está em triagem (research R8).
     */
    public function etapaNaVaga(Vaga $vaga, Candidato $candidato): EtapaSelecao
    {
        return $this->participacoes->encontrar($vaga, $candidato)?->etapa ?? EtapaSelecao::Triagem;
    }

    public function podeRevelar(Vaga $vaga, Candidato $candidato): bool
    {
        return $this->etapaNaVaga($vaga, $candidato)->revelaIdentidade();
    }

    /**
     * Ativo e do tipo aceito pela vaga.
     */
    public function elegivel(Vaga $vaga, Candidato $candidato): bool
    {
        return $candidato->status === StatusCandidato::Ativo && $vaga->tipo_candidato->aceita($candidato->tipo);
    }

    /**
     * Aparece no quadro: é elegível ou já saiu da triagem nesta vaga (mesmo desativado).
     */
    public function participaDoQuadro(Vaga $vaga, Candidato $candidato): bool
    {
        return $this->elegivel($vaga, $candidato) || $this->podeRevelar($vaga, $candidato);
    }

    public function perfilNaVaga(Vaga $vaga, Candidato $candidato): PerfilNaVagaDTO
    {
        $etapa = $this->etapaNaVaga($vaga, $candidato);
        $revelado = $etapa->revelaIdentidade();
        $candidato = $this->candidatos->detalhar($candidato);
        $termos = $revelado ? [] : $this->termosDoNome($candidato->nome);
        $textos = fn (?array $itens): array => array_values(array_map(
            fn (string $item): string => $revelado ? $item : $this->limpador->limpar($item, $termos),
            $itens ?? [],
        ));

        return new PerfilNaVagaDTO(
            codigo: $candidato->codigo,
            identidadeRevelada: $revelado,
            etapa: $etapa,
            tipo: $candidato->tipo,
            area: $candidato->area,
            experienciaNaArea: $candidato->experiencia_na_area,
            formacoes: $textos($candidato->formacoes),
            experiencias: $textos($candidato->experiencias),
            projetos: $textos($candidato->projetos),
            competencias: $candidato->competencias,
            resultado: $this->calculadora->calcular($this->vagas->requisitos($vaga), $candidato->competencias),
            quantidadeLinks: count($candidato->links ?? []),
            movimentacoes: $this->participacoes->historico($vaga, $candidato),
            podeMover: $vaga->estaAberta() && $this->elegivel($vaga, $candidato),
            desativado: $candidato->status === StatusCandidato::Desativado,
            nome: $revelado ? $candidato->nome : null,
            email: $revelado ? $candidato->email : null,
            telefone: $revelado ? $candidato->telefone : null,
            idade: $revelado ? $candidato->idade : null,
            links: $revelado ? array_values($candidato->links ?? []) : null,
            anotacoes: $revelado ? $this->anotacoes->historico($candidato) : null,
            temCurriculo: $revelado && $this->candidatos->obterCurriculo($candidato) !== null,
            energia: $this->energia->sugestaoNaVaga($candidato),
            origemEnergia: $candidato->perfilEnergia?->origem,
            energiaAnalisadaEm: $candidato->perfilEnergia?->analisado_em->format('d/m/Y H:i'),
            proximidadeEnergia: $this->energia->proximidadeNaVaga($vaga, $candidato),
        );
    }

    /**
     * O nome completo e cada parte com três letras ou mais, para a limpeza dos textos da triagem.
     *
     * @return list<string>
     */
    private function termosDoNome(string $nome): array
    {
        $partes = array_filter(
            preg_split('/\s+/u', trim($nome)) ?: [],
            fn (string $parte): bool => mb_strlen($parte) >= 3 && ! in_array(mb_strtolower($parte), self::PARTICULAS, true),
        );

        return array_values(array_unique([trim($nome), ...$partes]));
    }
}
