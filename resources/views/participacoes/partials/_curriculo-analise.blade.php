<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
    <section aria-labelledby="visualizacao-pdf-titulo" class="flex min-w-0 flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 id="visualizacao-pdf-titulo" class="text-lg font-semibold">Currículo original</h3>
            @if ($perfil->temCurriculo)
                <x-botao :href="route('vagas.candidatos.curriculo', [$vaga, $perfil->codigo])" variante="secundario" tamanho="sm" target="_blank" rel="noopener">Abrir PDF em nova aba</x-botao>
            @endif
        </div>
        @if ($perfil->temCurriculo)
            <p class="text-sm text-texto-suave">Se a prévia não aparecer, abra o PDF em uma nova aba.</p>
            <iframe src="{{ route('vagas.candidatos.curriculo', [$vaga, $perfil->codigo]) }}" title="Currículo original de {{ $perfil->nome }}" class="h-[65dvh] min-h-80 w-full rounded-lg border border-borda bg-fundo"></iframe>
        @else
            <p class="rounded-lg border border-dashed border-borda p-5 text-sm text-texto-suave">Nenhum PDF disponível para este candidato.</p>
        @endif
    </section>

    <div class="flex min-w-0 flex-col gap-6">
        <div id="visualizacao-energia">
            @include('participacoes.partials._analise-energia')
        </div>

        <section aria-labelledby="visualizacao-aderencia-titulo" class="flex flex-col gap-3 border-t border-borda pt-5">
            <h3 id="visualizacao-aderencia-titulo" class="text-lg font-semibold">Aderência a esta vaga</h3>
            <x-barra-score :percentual="$perfil->resultado->percentual" />
            <p class="text-sm leading-relaxed">{{ $perfil->resultado->explicacao() }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($perfil->resultado->atendidas as $competencia)
                    <x-tag-competencia :nome="$competencia['nome']" :origem="$competencia['origem']" :peso="$competencia['peso']" />
                @endforeach
                @foreach ($perfil->resultado->naoIdentificadas as $competencia)
                    <x-tag-competencia :nome="$competencia['nome']" :faltante="true" :peso="$competencia['peso']" />
                @endforeach
            </div>
        </section>
    </div>
</div>
