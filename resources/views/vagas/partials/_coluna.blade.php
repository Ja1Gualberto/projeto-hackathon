{{--
    Uma coluna do quadro da seleção. `data-etapa` também marca a coluna como destino do arrastar
    e soltar (kanban.js), que envia o mesmo formulário dos botões do card.
--}}
@php
    $total = $itens->count();
    $borda = match ($etapa) {
        \App\Enums\EtapaSelecao::Triagem => 'border-t-slate-400',
        \App\Enums\EtapaSelecao::Entrevista => 'border-t-primaria-600',
        \App\Enums\EtapaSelecao::Aprovado => 'border-t-score-alto',
    };
    $vazio = match ($etapa) {
        \App\Enums\EtapaSelecao::Triagem => 'Todos os elegíveis já avançaram nesta vaga.',
        \App\Enums\EtapaSelecao::Entrevista => 'Ninguém em entrevista ainda.',
        \App\Enums\EtapaSelecao::Aprovado => 'Nenhum candidato aprovado ainda.',
    };
@endphp

<section data-etapa="{{ $etapa->value }}" aria-labelledby="coluna-{{ $etapa->value }}" class="flex min-w-0 flex-col gap-3 rounded-card border border-t-4 border-borda {{ $borda }} bg-fundo p-3 transition-shadow data-alvo-soltar:ring-2 data-alvo-soltar:ring-primaria-500">
    <header class="flex items-center justify-between gap-2 px-1">
        <h3 id="coluna-{{ $etapa->value }}" class="text-lg font-semibold">{{ $etapa->rotulo() }}</h3>
        <span class="rounded-full bg-superficie px-2.5 py-0.5 text-sm font-semibold tabular-nums ring-1 ring-borda">
            {{ $total }}<span class="sr-only"> {{ $total === 1 ? 'candidato' : 'candidatos' }}</span>
        </span>
    </header>

    @if ($etapa === \App\Enums\EtapaSelecao::Triagem)
        <p class="flex items-center gap-1.5 px-1 text-sm text-texto-suave"><x-icone nome="cadeado" class="size-4" />Só o código aparece nesta coluna.</p>
    @endif

    @if ($itens->isEmpty())
        @if ($etapa === \App\Enums\EtapaSelecao::Triagem && $totalElegiveis === 0)
            <x-estado-vazio icone="grupo" titulo="Nenhum candidato elegível" texto="Ainda não há candidatos ativos do tipo aceito por esta vaga. Novos cadastros compatíveis entram aqui automaticamente." />
        @else
            <p class="rounded-lg border border-dashed border-borda px-3 py-6 text-center text-sm text-texto-suave">{{ $vazio }}</p>
        @endif
    @else
        <ol class="flex flex-col gap-3" aria-label="Candidatos em {{ $etapa->rotulo() }}, por aderência">
            @foreach ($itens as $item)
                <li>@include('vagas.partials._card-ranking', ['item' => $item, 'posicao' => $loop->iteration])</li>
            @endforeach
        </ol>
    @endif
</section>
