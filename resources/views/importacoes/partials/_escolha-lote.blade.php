{{--
    Opções de um lote de importação: revisar um por um ou cadastrar sem revisão. Usada na modal e no
    fallback sem JavaScript. Variáveis: $lote, $resumo e $contextoEscolha (sufixo dos ids dos campos).
--}}
@php
    $aRevisar = $resumo['pendentes'] - $resumo['aptos'];
    $meios = collect(\App\Enums\MeioConsentimento::cases())->mapWithKeys(fn ($meio) => [$meio->value => $meio->rotulo()])->all();
@endphp

<div class="flex flex-col gap-5">
    <div class="flex flex-col gap-2">
        <p class="leading-relaxed">Vários currículos foram importados. Como você prefere continuar?</p>
        <div class="flex flex-wrap gap-2">
            <x-badge cor="primaria">{{ $resumo['aptos'] }} {{ $resumo['aptos'] === 1 ? 'pronto' : 'prontos' }} para cadastro direto</x-badge>
            @if ($aRevisar > 0)
                <x-badge cor="aviso">{{ $aRevisar }} {{ $aRevisar === 1 ? 'precisa' : 'precisam' }} de revisão manual</x-badge>
            @endif
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <section class="flex flex-col gap-3 rounded-card border border-borda p-5" aria-labelledby="revisar-titulo-{{ $contextoEscolha }}">
            <h3 id="revisar-titulo-{{ $contextoEscolha }}" class="text-lg font-semibold">Revisar um por um</h3>
            <p class="flex-1 text-sm leading-relaxed text-texto-suave">Confira cada currículo ao lado do PDF, corrija o que for preciso e registre o consentimento de cada candidato.</p>
            <x-botao :href="route('importacoes.show', $resumo['proximo'])" tamanho="lg">Começar a revisão<x-icone nome="seta" class="size-5" /></x-botao>
        </section>

        <section class="flex flex-col gap-3 rounded-card border border-borda p-5" aria-labelledby="cadastrar-titulo-{{ $contextoEscolha }}">
            <h3 id="cadastrar-titulo-{{ $contextoEscolha }}" class="text-lg font-semibold">Cadastrar sem revisão</h3>
            @if ($resumo['aptos'] === 0)
                <p class="text-sm leading-relaxed text-texto-suave">Nenhum currículo está pronto para cadastro direto. Use a revisão manual.</p>
            @else
                <p class="text-sm leading-relaxed text-texto-suave">Salva os dados como foram lidos. Possíveis duplicados e leituras incertas ficam para você revisar.</p>
                <form method="POST" action="{{ route('importacoes.lotes.cadastrar', $lote) }}" data-ajax class="flex flex-col gap-3">
                    @csrf
                    <div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>
                    <p class="text-sm leading-relaxed">A LGPD exige o consentimento. Informe como e quando foi obtido; o registro vale para todos os cadastrados agora.</p>
                    <x-campo nome="consentimento_meio" :id="'lote-consentimento-meio-'.$contextoEscolha" rotulo="Meio do consentimento" tipo="select" vazio="Selecione como foi obtido" :opcoes="$meios" obrigatorio />
                    <x-campo nome="consentimento_em" :id="'lote-consentimento-em-'.$contextoEscolha" rotulo="Data do consentimento" tipo="date" :max="now()->toDateString()" obrigatorio />
                    <x-botao type="submit" variante="secundario" tamanho="lg">Cadastrar {{ $resumo['aptos'] }} sem revisão</x-botao>
                </form>
            @endif
        </section>
    </div>
</div>
