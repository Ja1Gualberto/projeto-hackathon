@extends('layouts.app')

@section('titulo', 'Painel')

@section('conteudo')
    <x-cabecalho-pagina titulo="Banco de Taletos da SETIC" subtitulo="Consulte as competências do banco e encontre perfis para sua equipe.">
        <x-botao :href="route('candidatos.index')"><x-icone nome="busca" class="size-4" />Explorar Candidatos</x-botao>
    </x-cabecalho-pagina>

    <section aria-label="Resumo do banco" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-cartao-estatistica rotulo="Talentos" :valor="$totalTalentos" detalhe="Cadastros ativos" class="border-t-2 border-transparent" />
        <x-cartao-estatistica rotulo="Vagas abertas" :valor="$vagasAbertas" detalhe="Processos em andamento" class="border-t-2 border-transparent" />
    </section>

    <div class="grid items-start gap-8 xl:grid-cols-[minmax(0,1fr)_18rem]">
        <section aria-labelledby="competencias-titulo" class="rounded-card border border-borda bg-superficie p-6 sm:p-8">
            <div class="mb-7 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="competencias-titulo" class="text-xl font-semibold tracking-tight">Competências mais frequentes</h2>
                    <p class="mt-1 text-sm text-texto-suave">Presença nos perfis ativos. Selecione uma competência para explorar.</p>
                </div>
                <span class="text-sm text-texto-suave">Até 8 competências</span>
            </div>

            @if ($topCompetencias->isNotEmpty())
                <ul class="flex flex-col gap-5">
                    @foreach ($topCompetencias as $competencia)
                        <li>
                            <a href="{{ route('candidatos.index', ['competencias' => [$competencia->id]]) }}" class="group block rounded-sm">
                                <div class="mb-2 flex items-center justify-between gap-4">
                                    @if ($competencia->iconeUrl())
                                        <img src="{{ $competencia->iconeUrl() }}" alt="{{ $competencia->nome }}" title="{{ $competencia->nome }}" class="size-8 object-contain">
                                    @else
                                        <span class="font-medium group-hover:text-primaria-700 group-hover:underline">{{ $competencia->nome }}</span>
                                    @endif
                                    <span class="shrink-0 text-sm text-texto-suave"><strong class="font-semibold tabular-nums text-texto">{{ $competencia->total }}</strong> {{ $competencia->total === 1 ? 'talento' : 'talentos' }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-sm bg-fundo" aria-hidden="true">
                                    <div class="h-full rounded-sm bg-primaria-600" style="width: {{ round($competencia->total / max(1, $totalTalentos) * 100) }}%"></div>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-7 border-t border-borda pt-4 text-sm text-texto-suave">Uma pessoa pode ter mais de uma competência. Os totais não representam uma divisão do banco.</p>
            @else
                <x-estado-vazio titulo="Nenhuma competência cadastrada nos perfis" texto="As competências aparecerão aqui quando houver talentos cadastrados no banco." />
            @endif
        </section>

        <aside aria-label="Ações e orientações" class="flex flex-col gap-7">
            <a href="{{ route('candidatos.index', ['situacao' => 'retencao']) }}" class="rounded-card focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primaria-600">
                <x-cartao-estatistica rotulo="Retenção pendente" :valor="$retencaoPendente" detalhe="Cadastros ativos que precisam de revisão" />
            </a>
            <section class="border-l-2 border-primaria-600 pl-5">
                <h2 class="text-xl font-semibold tracking-tight">Busque pela combinação</h2>
                <p class="mt-3 leading-relaxed text-texto-suave">Combine competências, área e processo seletivo. O resultado reúne quem atende a todos os critérios.</p>
                <a href="{{ route('candidatos.index') }}" class="mt-4 inline-flex font-semibold text-primaria-700 underline decoration-primaria-200 underline-offset-4 hover:decoration-primaria-700">Consultar o banco</a>
            </section>
            <section class="flex flex-col gap-3 border-t border-borda pt-6">
                <h2 class="font-semibold">Organize as próximas seleções</h2>
                <x-botao :href="route('importacoes.create')" variante="secundario">
                    <x-icone nome="upload" class="size-4" />Importar currículo
                </x-botao>
                <x-botao :href="route('vagas.create')" variante="secundario">
                    <x-icone nome="vaga" class="size-4" />Nova vaga
                </x-botao>
            </section>
        </aside>
    </div>
@endsection
