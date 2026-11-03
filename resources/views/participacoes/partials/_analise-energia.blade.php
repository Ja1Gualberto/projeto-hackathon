<section aria-labelledby="visualizacao-ia-titulo" class="flex flex-col gap-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h3 id="visualizacao-ia-titulo" tabindex="-1" class="min-w-0 flex-1 text-lg font-semibold">Análise de IA · Padrão de energia</h3>
        <form method="POST" action="{{ route('vagas.candidatos.energia.analisar', [$vaga, $perfil->codigo]) }}" data-gerar-analise-modal class="ml-auto shrink-0">
            @csrf
            <x-botao type="submit" tamanho="sm" data-gerar-analise-botao>{{ $perfil->energia ? 'Gerar novamente' : 'Gerar análise de IA' }}</x-botao>
        </form>
    </div>
    <p class="text-sm text-texto-suave">Sugestão revisável, não um diagnóstico. Não altera a aderência ou a ordenação.</p>
    <p data-analise-status role="status" hidden class="text-sm text-texto-suave"></p>
    <p data-analise-erro role="alert" hidden class="text-sm text-red-800"></p>
    @if ($perfil->energia)
        <div class="flex flex-wrap items-center gap-2 text-sm text-texto-suave">
            <x-badge>{{ $perfil->origemEnergia->rotulo() }}</x-badge>
            <span>{{ $perfil->energiaAnalisadaEm }}</span>
        </div>
        <x-eixos-energia :eixos="$perfil->energia->eixos()" :vaga="$vaga->temPadraoEnergia() ? ['dinamico_analitico' => $vaga->energia_dinamico_analitico, 'individual_equipe' => $vaga->energia_individual_equipe, 'execucao_planejamento' => $vaga->energia_execucao_planejamento] : null" />
        <p class="text-sm leading-relaxed whitespace-pre-line wrap-anywhere">{{ $perfil->energia->justificativa ?: 'Justificativa não informada.' }}</p>
        @if ($perfil->proximidadeEnergia !== null)
            <p class="text-sm font-semibold">Energia: {{ $perfil->proximidadeEnergia }}% de proximidade com a vaga</p>
        @endif
    @else
        <p class="rounded-lg border border-dashed border-borda p-5 text-sm text-texto-suave">Nenhuma análise de IA disponível para este candidato.</p>
    @endif
</section>
