{{--
    Card do quadro da vaga (contracts/rotas-web.md → "Conteúdo do card de ranking"). Recebe só o
    ItemRankingDTO: o nome vem preenchido apenas em Entrevista e Aprovado; contato, idade, links,
    PDF e link para /candidatos/{id} nunca aparecem aqui. O link do card abre o perfil na vaga, pelo código.
--}}
@php
    $identificacao = $item->nome ?? $item->codigo;
    $podeMover = $vaga->estaAberta() && $item->elegivel;
    $acoes = match ($item->etapa) {
        \App\Enums\EtapaSelecao::Triagem => [[\App\Enums\EtapaSelecao::Entrevista, 'Mover para Entrevista', 'primario']],
        \App\Enums\EtapaSelecao::Entrevista => [[\App\Enums\EtapaSelecao::Aprovado, 'Aprovar', 'primario'], [\App\Enums\EtapaSelecao::Triagem, 'Voltar para Triagem', 'secundario']],
        \App\Enums\EtapaSelecao::Aprovado => [[\App\Enums\EtapaSelecao::Entrevista, 'Voltar para Entrevista', 'secundario']],
    };
@endphp

<article data-codigo="{{ $item->codigo }}" @if ($podeMover) draggable="true" @endif aria-labelledby="card-{{ $item->codigo }}" @class([
    'flex flex-col gap-3 rounded-card border border-borda bg-superficie p-4 shadow-xs',
    'cursor-grab active:cursor-grabbing' => $podeMover,
    'opacity-60' => $item->desativado,
])>
    <div class="flex items-start gap-3">
        @isset($posicao)
            <span class="flex h-7 min-w-7 shrink-0 items-center justify-center rounded-full bg-primaria-50 px-2 text-sm font-bold text-primaria-800 tabular-nums">
                <span class="sr-only">Posição </span>{{ $posicao }}º
            </span>
        @endisset
        <div class="flex min-w-0 flex-col">
            <h4 id="card-{{ $item->codigo }}" @class(['text-lg leading-snug font-semibold tracking-tight wrap-anywhere', 'font-mono' => $item->nome === null])>
                <a href="{{ route('vagas.candidatos.show', [$vaga, $item->codigo]) }}" data-link-perfil draggable="false" class="hover:text-primaria-700 hover:underline focus-visible:text-primaria-700">{{ $identificacao }}</a>
            </h4>
            @if ($item->nome !== null)
                <p class="font-mono text-sm text-texto-suave">{{ $item->codigo }}</p>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <x-badge-tipo :tipo="$item->tipo" class="normal-case tracking-normal" />
        <span class="text-sm text-texto-suave">{{ $item->area->rotulo() }}</span>
        @if ($item->desativado)
            <x-badge>Desativado</x-badge>
        @elseif (! $item->elegivel)
            <x-badge>Fora do tipo aceito pela vaga</x-badge>
        @endif
    </div>

    <x-barra-score :percentual="$item->resultado->percentual" />
    <p class="text-sm leading-relaxed">{{ $item->resultado->explicacao() }}</p>

    <div class="flex flex-wrap items-center gap-1.5">
        @foreach ($item->resultado->atendidas as $competencia)
            <x-tag-competencia :nome="$competencia['nome']" :origem="$competencia['origem']" :peso="$competencia['peso']" />
        @endforeach
        @foreach ($item->resultado->naoIdentificadas as $competencia)
            <x-tag-competencia :nome="$competencia['nome']" :faltante="true" :peso="$competencia['peso']" />
        @endforeach
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-texto-suave">
        <p>Experiência na área: {{ $item->experienciaNaArea ? 'sim' : 'não informado' }}</p>
        @include('vagas.partials._selo-energia', ['item' => $item])
    </div>

    @if ($podeMover)
        {{-- Sem JavaScript, cada botão envia a etapa pelo próprio name/value; com JavaScript, kanban.js usa o mesmo formulário. --}}
        <form method="POST" action="{{ route('vagas.candidatos.etapa', [$vaga, $item->codigo]) }}" data-mover-etapa class="flex flex-wrap gap-2 border-t border-borda pt-3">
            @csrf
            @method('PATCH')
            <input type="hidden" name="contexto" value="quadro">
            @foreach ($acoes as [$destino, $rotulo, $variante])
                <x-botao type="submit" name="etapa" :value="$destino->value" :variante="$variante" tamanho="sm">{{ $rotulo }}<span class="sr-only">: {{ $identificacao }}</span></x-botao>
            @endforeach
        </form>
    @endif
</article>
