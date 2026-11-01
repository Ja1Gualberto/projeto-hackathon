{{--
    Conteúdo da região "quadro-vaga" (contracts/rotas-web.md): cabeçalho, situação, ações e o
    ranking da triagem. A mesma marcação serve a página e o fragmento AJAX ("Atualizar quadro" e
    "Encerrar vaga"). Os cards recebem só ItemRankingDTO, sem identidade nem link para o banco.
--}}
@php
    $aberta = $vaga->estaAberta();
    $totalElegiveis = $ranking->count();
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
        <x-alerta tipo="aviso" titulo="Vaga encerrada — somente consulta">
            Encerrada em {{ $vaga->encerrada_em?->format('d/m/Y') }}. O ranking continua disponível para consulta; a vaga não aceita mais edições nem movimentações.
        </x-alerta>
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

    <section aria-labelledby="triagem-titulo" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-baseline justify-between gap-3 border-b border-borda pb-3">
            <h2 id="triagem-titulo" class="text-xl font-semibold tracking-tight">
                Triagem <span class="font-normal text-texto-suave">· {{ $totalElegiveis }} {{ $totalElegiveis === 1 ? 'candidato elegível' : 'candidatos elegíveis' }}</span>
            </h2>
            <p class="flex items-center gap-2 text-sm text-texto-suave">
                <x-icone nome="cadeado" class="size-4" />
                Identidade protegida: só o código aparece. Ordem por aderência.
            </p>
        </div>

        @if ($ranking->isEmpty())
            <x-estado-vazio icone="grupo" titulo="Nenhum candidato elegível" texto="Ainda não há candidatos ativos do tipo aceito por esta vaga. Novos cadastros compatíveis entram aqui automaticamente." />
        @else
            <ol class="flex flex-col gap-3" aria-label="Ranking da triagem">
                @foreach ($ranking as $item)
                    <li>@include('vagas.partials._card-ranking', ['item' => $item, 'posicao' => $loop->iteration])</li>
                @endforeach
            </ol>
        @endif
    </section>
</div>
