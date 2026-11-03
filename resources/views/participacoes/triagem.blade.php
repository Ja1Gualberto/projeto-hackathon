@extends('layouts.app')

@section('titulo', 'Selecionar candidatos')

@section('conteudo')
    <nav aria-label="Localização">
        <a href="{{ route('vagas.show', $vaga) }}" class="inline-flex items-center gap-2 text-sm font-medium text-primaria-700 underline underline-offset-4">
            <x-icone nome="seta" class="size-4 rotate-180" />
            Voltar ao quadro da vaga
        </a>
    </nav>
    <x-cabecalho-pagina titulo="Selecionar candidatos" :subtitulo="$vaga->titulo.' — filtre por competências e marque quem deseja adicionar à triagem.'">
        <x-botao :href="route('importacoes.create', ['vaga_id' => $vaga->id])" variante="secundario">Importar currículos para esta vaga</x-botao>
    </x-cabecalho-pagina>

    @if ($errors->any())
        <x-alerta tipo="erro"><ul class="list-inside list-disc">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></x-alerta>
    @endif

    @php
        $competenciasDoCatalogo = collect($catalogo)->flatten(1);
        $gruposCompetencias = $competenciasDoCatalogo->groupBy(fn ($competencia) => match (true) {
            $competencia->categoria === \App\Enums\CategoriaCompetencia::Soft => 'Comportamentais',
            $competencia->ehIdioma() => 'Idiomas',
            default => $competencia->area?->rotulo() ?? 'Outras competências técnicas',
        })->sortKeys();
    @endphp

    <form method="GET" action="{{ route('vagas.triagem.index', $vaga) }}" data-busca-candidatos data-filtro-competencias class="flex min-w-0 flex-col gap-3 rounded-card border border-borda bg-superficie p-4">
        <input type="hidden" name="busca" value="">
        <input type="hidden" name="filtros_aplicados" value="1" data-valor-padrao="1">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <x-botao variante="secundario" aria-expanded="false" aria-controls="painel-competencias" data-abrir-competencias>
                <span data-contagem-competencias>Competências{{ count($competenciasSelecionadas) ? ' ('.count($competenciasSelecionadas).')' : '' }}</span>
                <x-icone nome="chevron-down" class="size-4 shrink-0" />
            </x-botao>
            <div data-etiquetas-competencias role="group" class="flex min-w-0 flex-wrap gap-2" aria-label="Competências aplicadas">
                @foreach ($competenciasDoCatalogo->whereIn('id', $competenciasSelecionadas) as $competencia)
                    @if (in_array($competencia->id, $competenciasDaVaga, true))
                        <span data-competencia-fixa title="Competência da vaga — não pode ser removida" class="inline-flex max-w-full items-center gap-2 rounded-lg bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200 ring-inset">
                            <x-icone nome="cadeado" class="size-3.5" /><span class="sr-only">Da vaga, fixa: </span><span class="min-w-0 wrap-anywhere">{{ $competencia->nome }}</span>
                        </span>
                    @else
                        <x-botao variante="fantasma" tamanho="sm" data-remover-competencia="{{ $competencia->id }}" aria-label="Remover {{ $competencia->nome }}" class="max-w-full bg-primaria-50 text-left ring-1 ring-primaria-200 ring-inset">
                            <span class="min-w-0 wrap-anywhere">{{ $competencia->nome }}</span><span aria-hidden="true">×</span>
                        </x-botao>
                    @endif
                @endforeach
            </div>
            <span data-limpar-competencias @if (array_diff($competenciasSelecionadas, $competenciasDaVaga) === [] && $filtro->busca === null) hidden @endif>
                <x-botao :href="route('vagas.triagem.index', [$vaga, 'busca' => '', 'filtros_aplicados' => 1])" data-limpar-busca variante="fantasma" tamanho="sm">Limpar adicionais</x-botao>
            </span>
        </div>
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs" aria-label="Legenda das competências" role="group">
            <span class="inline-flex items-center gap-1.5 text-emerald-800"><span aria-hidden="true" class="size-2.5 rounded-full bg-emerald-600"></span>Da vaga · fixas</span>
            <span class="inline-flex items-center gap-1.5 text-primaria-800"><span aria-hidden="true" class="size-2.5 rounded-full bg-primaria-600"></span>Adicionadas por você · removíveis</span>
        </div>
        <ul id="regra-competencias" class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-texto-suave" aria-label="Como os resultados são exibidos">
            <li class="inline-flex items-center gap-1.5"><x-icone nome="check" class="size-3.5 text-primaria-700" />Basta corresponder a 1 competência</li>
            <li class="inline-flex items-center gap-1.5"><x-icone nome="check" class="size-3.5 text-primaria-700" />Mais correspondências primeiro</li>
            <li class="inline-flex items-center gap-1.5"><x-icone nome="check" class="size-3.5 text-primaria-700" />Já vinculados ficam de fora</li>
        </ul>

        <section id="painel-competencias" hidden data-painel-competencias data-sem-autoenvio aria-label="Filtrar por competências" class="border-t border-borda pt-4">
            <div class="flex flex-col gap-4">
                <x-campo nome="" id="buscar-competencia" rotulo="Buscar competência" tipo="search" data-buscar-competencia autocomplete="off" />
                <fieldset aria-describedby="regra-competencias" class="min-w-0">
                    <legend class="sr-only">Filtrar por competências</legend>
                    <div class="grid max-h-80 gap-5 overflow-y-auto p-1 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($gruposCompetencias as $grupo => $competencias)
                            <div data-grupo-competencias class="min-w-0">
                                <h2 class="mb-2 text-sm font-semibold">{{ $grupo }}</h2>
                                <div class="flex flex-col gap-1">
                                    @foreach ($competencias as $competencia)
                                        <label data-opcao-competencia class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-2 text-sm hover:bg-fundo has-checked:bg-primaria-50 has-disabled:cursor-default has-disabled:bg-emerald-50 has-disabled:text-emerald-800">
                                            <input type="checkbox" name="competencias[]" value="{{ $competencia->id }}" @checked(in_array($competencia->id, $competenciasSelecionadas, true)) @disabled(in_array($competencia->id, $competenciasDaVaga, true)) data-nome-competencia="{{ $competencia->nome }}" class="mt-0.5 size-4 shrink-0 accent-primaria-600 disabled:accent-emerald-600">
                                            <span class="min-w-0 wrap-anywhere">{{ $competencia->nome }}</span>
                                            @if (in_array($competencia->id, $competenciasDaVaga, true))
                                                <span class="ml-auto inline-flex shrink-0 items-center gap-1 text-xs"><x-icone nome="cadeado" class="size-3.5" />Da vaga</span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
                <p hidden data-competencias-vazias role="status" class="text-sm text-texto-suave">Nenhuma competência encontrada</p>
                <div class="flex flex-wrap gap-2 border-t border-borda pt-4">
                    <x-botao type="submit">Aplicar filtros</x-botao>
                    <x-botao variante="secundario" data-fechar-competencias>Cancelar</x-botao>
                </div>
            </div>
        </section>
        <template data-modelo-etiqueta>
            <x-botao variante="fantasma" tamanho="sm" class="max-w-full bg-primaria-50 text-left ring-1 ring-primaria-200 ring-inset">
                <span data-nome-etiqueta class="min-w-0 wrap-anywhere"></span><span aria-hidden="true">×</span>
            </x-botao>
        </template>
        <template data-modelo-etiqueta-fixa>
            <span data-competencia-fixa title="Competência da vaga — não pode ser removida" class="inline-flex max-w-full items-center gap-2 rounded-lg bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200 ring-inset">
                <x-icone nome="cadeado" class="size-3.5" /><span class="sr-only">Da vaga, fixa: </span><span data-nome-etiqueta class="min-w-0 wrap-anywhere"></span>
            </span>
        </template>
    </form>

    <section id="resultados-candidatos" aria-labelledby="resultados-titulo" class="flex min-w-0 flex-col gap-6 transition-opacity aria-busy:cursor-progress aria-busy:opacity-60 motion-reduce:transition-none">
        @include('participacoes.partials._resultados-triagem')
    </section>
@endsection
