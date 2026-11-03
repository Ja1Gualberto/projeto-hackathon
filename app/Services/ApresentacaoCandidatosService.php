<?php

namespace App\Services;

use App\DTOs\FiltroCandidatosDTO;
use App\Enums\Area;
use App\Enums\StatusCandidato;
use App\Enums\TipoCandidato;
use App\Models\Candidato;
use App\Models\Competencia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\ComponentSlot;

final class ApresentacaoCandidatosService
{
    /**
     * @param  array{hard: Collection<int, Competencia>, soft: Collection<int, Competencia>}  $catalogo
     * @return array{urlBusca: string, urlImportacao: string, campos: array<string, HtmlString>, experiencia: ComponentAttributeBag, catalogoHtml: array<string, HtmlString>, catalogoVazio: array<string, ComponentAttributeBag>, errosFiltros: HtmlString, alertaErros: ComponentAttributeBag, urlArteErro: string, filtrosHtml: HtmlString}
     */
    public function filtros(FiltroCandidatosDTO $filtro, array $catalogo, ViewErrorBag $erros): array
    {
        $catalogoHtml = [];
        $catalogoVazio = [];

        foreach ($catalogo as $categoria => $competencias) {
            $chips = [];

            foreach ($competencias as $competencia) {
                $chips[] = $this->renderizar('candidatos.partials._chip-competencia', [
                    'id' => $competencia->id,
                    'nome' => $competencia->nome,
                    'marcado' => new ComponentAttributeBag(['checked' => in_array($competencia->id, $filtro->competencias, true)]),
                ]);
            }

            $catalogoHtml[$categoria] = $this->juntar($chips);
            $catalogoVazio[$categoria] = new ComponentAttributeBag(['hidden' => $chips !== []]);
        }

        $itensErro = [];

        foreach ($erros->all() as $erro) {
            $itensErro[] = '<li>'.e($erro).'</li>';
        }

        $dados = [
            'urlBusca' => route('candidatos.index'),
            'urlImportacao' => route('importacoes.create'),
            'campos' => [
                'busca' => $this->componente('campo', [
                    'nome' => 'busca', 'rotulo' => 'Nome ou e-mail', 'tipo' => 'search', 'valor' => $filtro->busca,
                    'attributes' => new ComponentAttributeBag(['maxlength' => 100, 'placeholder' => 'Quem você procura?']),
                ]),
                'situacao' => $this->componente('campo', [
                    'nome' => 'situacao', 'rotulo' => 'Situação', 'tipo' => 'select', 'valor' => $filtro->situacao,
                    'opcoes' => ['ativos' => 'Ativos', 'desativados' => 'Desativados', 'retencao' => 'Retenção pendente'],
                    'attributes' => new ComponentAttributeBag(['data-valor-padrao' => 'ativos']),
                ]),
                'tipo' => $this->componente('campo', [
                    'nome' => 'tipo', 'rotulo' => 'Tipo de candidato', 'tipo' => 'select', 'valor' => $filtro->tipo,
                    'vazio' => 'Todos os tipos',
                    'opcoes' => collect(TipoCandidato::cases())->mapWithKeys(fn (TipoCandidato $tipo): array => [$tipo->value => $tipo->rotulo()])->all(),
                ]),
                'area' => $this->componente('campo', [
                    'nome' => 'area', 'rotulo' => 'Área de atuação', 'tipo' => 'select', 'valor' => $filtro->area,
                    'vazio' => 'Todas as áreas',
                    'opcoes' => collect(Area::cases())->mapWithKeys(fn (Area $area): array => [$area->value => $area->rotulo()])->all(),
                ]),
            ],
            'experiencia' => new ComponentAttributeBag(['checked' => $filtro->somenteComExperiencia]),
            'catalogoHtml' => $catalogoHtml,
            'catalogoVazio' => $catalogoVazio,
            'errosFiltros' => new HtmlString(implode('', $itensErro)),
            'alertaErros' => new ComponentAttributeBag(['hidden' => ! $erros->any(), 'data-alerta' => $erros->any()]),
            'urlArteErro' => asset('images/message/fail.svg'),
        ];

        return [...$dados, 'filtrosHtml' => $this->renderizar('candidatos.partials._filtros', $dados)];
    }

    /**
     * @param  LengthAwarePaginator<int, Candidato>  $candidatos
     * @param  array{hard: Collection<int, Competencia>, soft: Collection<int, Competencia>}  $catalogo
     */
    public function resultados(LengthAwarePaginator $candidatos, FiltroCandidatosDTO $filtro, array $catalogo): HtmlString
    {
        $selecionadas = [];

        foreach ($catalogo as $competencias) {
            foreach ($competencias as $competencia) {
                if (in_array($competencia->id, $filtro->competencias, true)) {
                    $selecionadas[] = $this->componente('tag-competencia', ['nome' => $competencia->nome]);
                }
            }
        }

        $cards = [];

        foreach ($candidatos as $candidato) {
            $cards[] = $this->card($candidato);
        }

        $estadoVazio = $cards === []
            ? $this->componente('estado-vazio', [
                'titulo' => 'Nenhum talento com esses critérios',
                'texto' => 'Remova uma competência ou amplie a área e o tipo de candidato para tentar novamente.',
            ], $this->componente('botao', [
                'href' => route('candidatos.index'), 'variante' => 'secundario',
                'attributes' => new ComponentAttributeBag(['data-limpar-busca' => true]),
            ], new HtmlString('Remover filtros')))
            : new HtmlString;

        return $this->renderizar('candidatos.partials._resultados', [
            'total' => $candidatos->total(),
            'rotuloTotal' => $candidatos->total() === 1 ? 'talento encontrado' : 'talentos encontrados',
            'situacao' => match ($filtro->situacao) {
                'desativados' => 'Perfis desativados',
                'retencao' => 'Perfis ativos com retenção pendente',
                default => 'Perfis ativos',
            },
            'selecionadas' => $this->juntar($selecionadas),
            'atributosSelecionadas' => new ComponentAttributeBag(['hidden' => $filtro->competencias === []]),
            'cards' => $this->juntar($cards),
            'estadoVazio' => $estadoVazio,
            'atributosVazio' => new ComponentAttributeBag(['hidden' => $cards !== []]),
            'paginacao' => $cards === [] ? new HtmlString : new HtmlString($candidatos->links('components.paginacao')->toHtml()),
            'atributosPaginacao' => new ComponentAttributeBag(['hidden' => $cards === []]),
        ]);
    }

    private function card(Candidato $candidato): HtmlString
    {
        $desativado = $candidato->status === StatusCandidato::Desativado;
        $retencaoVencida = $candidato->retencao_vencida;
        $competencias = [];

        foreach ($candidato->competencias as $competencia) {
            $competencias[] = $this->componente('tag-competencia', [
                'nome' => $competencia->nome,
                'attributes' => new ComponentAttributeBag([
                    'data-competencia-card' => true,
                    'class' => 'shrink-0 whitespace-nowrap group-data-[expandido=true]/competencias:max-w-full group-data-[expandido=true]/competencias:whitespace-normal group-data-[expandido=true]/competencias:wrap-anywhere',
                ]),
            ]);
        }

        return $this->renderizar('candidatos.partials._card', [
            'urlPerfil' => route('candidatos.show', $candidato),
            'nome' => $candidato->nome,
            'area' => $candidato->area->rotulo(),
            'experiencia' => $candidato->experiencia_na_area ? 'sim' : 'não informado',
            'tipo' => $this->componente('badge-tipo', [
                'tipo' => $candidato->tipo,
                'attributes' => new ComponentAttributeBag(['class' => 'normal-case tracking-normal']),
            ]),
            'seloDesativado' => $desativado ? $this->componente('badge', [], new HtmlString('Desativado')) : new HtmlString,
            'atributosDesativado' => new ComponentAttributeBag(['hidden' => ! $desativado]),
            'seloRetencao' => $retencaoVencida ? $this->componente('badge', ['cor' => 'aviso'], new HtmlString('revisão de retenção pendente')) : new HtmlString,
            'atributosRetencao' => new ComponentAttributeBag(['hidden' => ! $retencaoVencida]),
            'competencias' => $this->juntar($competencias),
            'idCompetencias' => 'competencias-candidato-'.$candidato->id,
            'atributosSemCompetencias' => new ComponentAttributeBag(['hidden' => $competencias !== []]),
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function componente(string $nome, array $dados = [], ?HtmlString $conteudo = null): HtmlString
    {
        return $this->renderizar('components.'.$nome, [...$dados, 'slot' => new ComponentSlot($conteudo?->toHtml() ?? '')]);
    }

    /** @param array<string, mixed> $dados */
    private function renderizar(string $nome, array $dados = []): HtmlString
    {
        return new HtmlString(view($nome, $dados)->render());
    }

    /** @param list<HtmlString> $fragmentos */
    private function juntar(array $fragmentos): HtmlString
    {
        return new HtmlString(implode('', $fragmentos));
    }
}
