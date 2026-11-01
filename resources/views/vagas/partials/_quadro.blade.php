{{--
    Conteúdo da região "quadro-vaga" (contracts/rotas-web.md): cabeçalho, situação, ações e as três
    colunas da seleção. A mesma marcação serve a página e os fragmentos AJAX ("Atualizar quadro",
    "Encerrar vaga" e as movimentações). Os cards recebem só ItemRankingDTO: o nome aparece apenas
    em Entrevista e Aprovado, e nunca há link para o perfil do banco.
--}}
@php
    $aberta = $vaga->estaAberta();
@endphp

<div class="flex flex-col gap-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex min-w-0 flex-col gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <x-badge :cor="$aberta ? 'sucesso' : 'neutro'">{{ $vaga->status->rotulo() }}</x-badge>
                <x-badge cor="primaria">{{ $vaga->tipo_candidato->rotulo() }}</x-badge>
                <span class="text-sm text-texto-suave">{{ $vaga->area->rotulo() }}</span>
            </div>
            <h1 tabindex="-1" data-foco-apos-ajax class="text-3xl font-bold tracking-tight text-texto focus:outline-none">{{ $vaga->titulo }}</h1>
            @if ($vaga->descricao)
                <p class="max-w-3xl leading-relaxed text-texto-suave">{{ $vaga->descricao }}</p>
            @endif
        </div>

        <div class="flex shrink-0 flex-wrap gap-3">
            <x-botao :href="route('vagas.show', $vaga)" variante="secundario" data-atualizar-quadro>Atualizar quadro</x-botao>
            @if ($aberta)
                <x-botao :href="route('vagas.edit', $vaga)" variante="secundario">Editar vaga</x-botao>
                <form method="POST" action="{{ route('vagas.status', $vaga) }}" data-ajax data-confirmar="Encerrar esta vaga? O quadro ficará disponível somente para consulta.">
                    @csrf
                    @method('PATCH')
                    <x-botao type="submit" variante="perigo">Encerrar vaga</x-botao>
                </form>
            @endif
        </div>
    </header>

    @unless ($aberta)
        <div class="flex flex-col gap-1 border-l-4 border-borda pl-4 text-sm text-texto-suave">
            <p class="font-semibold text-texto">Vaga encerrada — somente consulta</p>
            <p>Encerrada em {{ $vaga->encerrada_em?->format('d/m/Y') }}. O quadro e o histórico continuam disponíveis para consulta; a vaga não aceita mais edições nem movimentações.</p>
        </div>
    @endunless

    <section aria-labelledby="requisitos-titulo" class="rounded-card border border-borda bg-superficie p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="requisitos-titulo" class="font-semibold">Competências pedidas</h2>
            <p class="text-sm text-texto-suave">Soma dos pesos: <strong class="font-semibold text-texto tabular-nums">{{ $requisitos->sum(fn ($competencia) => (int) $competencia->pivot->peso) }}</strong></p>
        </div>
        <ul class="mt-3 flex flex-wrap gap-2">
            @foreach ($requisitos as $competencia)
                <li><x-tag-competencia :nome="$competencia->nome" :peso="(int) $competencia->pivot->peso" /></li>
            @endforeach
        </ul>
    </section>

    <section aria-labelledby="selecao-titulo" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-baseline justify-between gap-3 border-b border-borda pb-3">
            <h2 id="selecao-titulo" class="text-xl font-semibold tracking-tight">
                Seleção <span class="font-normal text-texto-suave">· {{ $totalElegiveis }} {{ $totalElegiveis === 1 ? 'candidato elegível' : 'candidatos elegíveis' }}</span>
            </h2>
            <p class="flex items-center gap-2 text-sm text-texto-suave">
                <x-icone nome="cadeado" class="size-4" />
                Triagem anônima: o nome aparece a partir da entrevista, só nesta vaga.
            </p>
        </div>

        <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)]">
            @foreach (\App\Enums\EtapaSelecao::cases() as $etapa)
                @include('vagas.partials._coluna', ['etapa' => $etapa, 'itens' => $colunas[$etapa->value]])
            @endforeach
        </div>
    </section>
</div>
