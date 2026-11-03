<?php

namespace App\Services;

use App\DTOs\ItemRankingDTO;
use App\Enums\Area;
use App\Enums\TipoCandidatoVaga;
use App\Models\Competencia;
use App\Models\Vaga;

/** Serializa apenas os dados necessários para a tela, sem renderizar HTML. */
class VagaTelaService
{
    /** @param array{vagas: iterable<Vaga>, elegiveis: array<int, int>} $lista */
    public function lista(array $lista): array
    {
        $vagas = collect($lista['vagas']);

        return [
            'vagas' => $vagas->map(fn (Vaga $vaga): array => [
                ...$this->vaga($vaga),
                'areaValor' => $vaga->area->value,
                'cor' => $vaga->area->cor(),
                'competencias_count' => $vaga->competencias_count,
                'entrevista_count' => $vaga->entrevista_count,
                'aprovado_count' => $vaga->aprovado_count,
                'totalElegiveis' => $lista['elegiveis'][$vaga->id] ?? 0,
            ])->all(),
            'areas' => collect(Area::cases())
                ->filter(fn (Area $area): bool => $vagas->contains(fn (Vaga $vaga): bool => $vaga->area === $area))
                ->map(fn (Area $area): array => ['valor' => $area->value, 'rotulo' => $area->rotulo(), 'cor' => $area->cor()])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, iterable<Competencia>>  $catalogo
     * @param  array<int, int>  $pesos
     */
    public function formulario(Vaga $vaga, array $catalogo, array $pesos): array
    {
        return [
            'valores' => [
                'titulo' => $vaga->titulo,
                'area' => $vaga->area?->value,
                'tipo_candidato' => $vaga->tipo_candidato?->value,
                'descricao' => $vaga->descricao,
                'competencias' => collect($pesos)->mapWithKeys(fn (int $peso, int $id): array => [$id => ['peso' => $peso]])->all(),
                'energia' => [
                    'dinamico_analitico' => $vaga->energia_dinamico_analitico,
                    'individual_equipe' => $vaga->energia_individual_equipe,
                    'execucao_planejamento' => $vaga->energia_execucao_planejamento,
                ],
            ],
            'areas' => array_map(fn (Area $area): array => ['valor' => $area->value, 'rotulo' => $area->rotulo()], Area::cases()),
            'tipos' => array_map(fn (TipoCandidatoVaga $tipo): array => ['valor' => $tipo->value, 'rotulo' => $tipo->rotulo()], TipoCandidatoVaga::cases()),
            'catalogo' => collect($catalogo)->map(fn (iterable $itens): array => collect($itens)->map(fn (Competencia $competencia): array => [
                'id' => $competencia->id, 'nome' => $competencia->nome,
            ])->all())->all(),
        ];
    }

    /** @param array{vaga: Vaga, requisitos: iterable<Competencia>, colunas: array<string, iterable<ItemRankingDTO>>, totalElegiveis: int} $quadro */
    public function quadro(array $quadro): array
    {
        $vaga = $quadro['vaga'];

        return [
            'vaga' => $this->vaga($vaga),
            'totalElegiveis' => $quadro['totalElegiveis'],
            'requisitos' => collect($quadro['requisitos'])->map(fn (Competencia $competencia): array => [
                'nome' => $competencia->nome, 'peso' => (int) $competencia->pivot->peso,
            ])->all(),
            'colunas' => collect($quadro['colunas'])->map(fn (iterable $itens): array => collect($itens)->map(fn (ItemRankingDTO $item): array => [
                'codigo' => $item->codigo,
                'nome' => $item->nome,
                'tipo' => $item->tipo->value,
                'area' => $item->area->rotulo(),
                'etapa' => $item->etapa->value,
                'elegivel' => $item->elegivel,
                'desativado' => $item->desativado,
                'experienciaNaArea' => $item->experienciaNaArea,
                'proximidadeEnergia' => $item->proximidadeEnergia,
                'percentual' => $item->resultado->percentual,
                'explicacao' => $item->resultado->explicacao(),
                'atendidas' => $item->resultado->atendidas,
                'naoIdentificadas' => $item->resultado->naoIdentificadas,
                'perfilUrl' => route('vagas.candidatos.show', [$vaga, $item->codigo]),
                'etapaUrl' => route('vagas.candidatos.etapa', [$vaga, $item->codigo]),
                'anotacaoUrl' => route('vagas.candidatos.anotacoes.store', [$vaga, $item->codigo]),
            ])->values()->all())->all(),
        ];
    }

    private function vaga(Vaga $vaga): array
    {
        return [
            'titulo' => $vaga->titulo,
            'descricao' => $vaga->descricao,
            'area' => $vaga->area->rotulo(),
            'tipo' => $vaga->tipo_candidato->rotulo(),
            'status' => $vaga->status->rotulo(),
            'aberta' => $vaga->estaAberta(),
            'encerradaEm' => $vaga->encerrada_em?->format('d/m/Y'),
            'temEnergia' => $vaga->temPadraoEnergia(),
            'url' => route('vagas.show', $vaga),
            'editarUrl' => $vaga->estaAberta() ? route('vagas.edit', $vaga) : null,
            'statusUrl' => route('vagas.status', $vaga),
        ];
    }
}
