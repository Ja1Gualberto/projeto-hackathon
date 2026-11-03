@php($energia = $candidato->perfilEnergia)
<section class="flex max-w-3xl flex-col gap-4" aria-labelledby="energia-titulo">
    <div class="flex flex-col gap-2">
        <h2 id="energia-titulo" tabindex="-1" data-foco-apos-ajax class="text-base font-semibold focus:outline-none">Padrão de energia</h2>
        <p class="text-sm leading-relaxed text-texto-suave">Sugestão revisável baseada no currículo, não um diagnóstico. Não altera a aderência nem a ordem do ranking. A decisão é humana.</p>
    </div>
    @if ($energia)
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <x-badge cor="primaria">{{ $energia->origem->rotulo() }}</x-badge>
            <span class="text-texto-suave">{{ $energia->analisado_em->format('d/m/Y H:i') }}</span>
        </div>
        <x-eixos-energia :eixos="['dinamico_analitico' => $energia->dinamico_analitico, 'individual_equipe' => $energia->individual_equipe, 'execucao_planejamento' => $energia->execucao_planejamento]" />
        <p class="max-w-prose text-sm leading-relaxed wrap-anywhere">{{ $energia->justificativa ?: 'Justificativa não informada.' }}</p>
    @else
        <p class="text-sm text-texto-suave">Não analisado</p>
    @endif
    @unless (config('banco_talentos.energia.habilitada') && filled(config('services.anthropic.key')))
        <p class="text-sm text-texto-suave">Análise indisponível: IA desligada ou sem chave. O ajuste manual continua disponível.</p>
    @endunless
    <div class="flex flex-wrap items-start gap-3">
        <form method="POST" action="{{ route('candidatos.energia.analisar', $candidato) }}" data-ajax class="flex flex-col gap-2">
            @csrf
            <div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>
            <x-botao type="submit" variante="secundario" data-carregando>Analisar com IA</x-botao>
        </form>
        <x-botao type="button" variante="secundario" data-abrir-modal="ajustar-energia">Ajustar manualmente</x-botao>
    </div>
    <x-modal id="ajustar-energia" titulo="Ajustar padrão de energia">
        @include('candidatos.partials._formulario-energia', ['sufixo' => 'modal'])
    </x-modal>
    <noscript>
        <details open class="rounded-lg border border-borda p-4">
            <summary class="font-semibold">Ajustar manualmente</summary>
            @include('candidatos.partials._formulario-energia', ['sufixo' => 'sem-js'])
        </details>
    </noscript>
</section>
