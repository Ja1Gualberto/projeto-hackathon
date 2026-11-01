{{--
    Card do ranking na triagem (contracts/rotas-web.md → "Conteúdo do card de ranking"). Recebe só
    o ItemRankingDTO: nunca nome, contato, idade, links, PDF ou link para /candidatos/{id}.
--}}
<article aria-labelledby="card-{{ $item->codigo }}" class="flex flex-col gap-4 rounded-card border border-borda bg-superficie p-5">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
        @isset($posicao)
            <span class="flex h-8 min-w-8 items-center justify-center rounded-full bg-primaria-50 px-2 text-sm font-bold text-primaria-800 tabular-nums">
                <span class="sr-only">Posição </span>{{ $posicao }}º
            </span>
        @endisset
        <h3 id="card-{{ $item->codigo }}" class="font-mono text-lg font-semibold tracking-tight">{{ $item->codigo }}</h3>
        <x-badge-tipo :tipo="$item->tipo" class="normal-case tracking-normal" />
        <span class="text-sm text-texto-suave">{{ $item->area->rotulo() }}</span>
    </div>

    <div class="grid gap-3 md:grid-cols-[minmax(0,16rem)_minmax(0,1fr)] md:items-center md:gap-6">
        <x-barra-score :percentual="$item->resultado->percentual" />
        <p class="leading-relaxed">{{ $item->resultado->explicacao() }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @foreach ($item->resultado->atendidas as $competencia)
            <x-tag-competencia :nome="$competencia['nome']" :origem="$competencia['origem']" :peso="$competencia['peso']" />
        @endforeach
        @foreach ($item->resultado->naoIdentificadas as $competencia)
            <x-tag-competencia :nome="$competencia['nome']" :faltante="true" :peso="$competencia['peso']" />
        @endforeach
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-borda pt-3 text-sm text-texto-suave">
        <p>Experiência na área: {{ $item->experienciaNaArea ? 'sim' : 'não informado' }}</p>
        @include('vagas.partials._selo-energia', ['item' => $item])
    </div>
</article>
