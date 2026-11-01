<section class="flex flex-col gap-5 border-t border-borda pt-6" aria-labelledby="lgpd-titulo">
    <h2 id="lgpd-titulo" class="text-xl font-semibold tracking-tight">Consentimento e retenção</h2>
    <dl class="grid gap-4 text-sm sm:grid-cols-2">
        <div><dt class="text-texto-suave">Meio do consentimento</dt><dd class="mt-1 font-medium">{{ $candidato->consentimento_meio->rotulo() }}</dd></div>
        <div><dt class="text-texto-suave">Data do consentimento</dt><dd class="mt-1 font-medium">{{ $candidato->consentimento_em->format('d/m/Y') }}</dd></div>
        <div><dt class="text-texto-suave">Registrado por</dt><dd class="mt-1 font-medium">{{ $candidato->consentimentoRegistradoPor?->name ?? 'Responsável não disponível' }}</dd></div>
        <div><dt class="text-texto-suave">Última atualização dos dados</dt><dd class="mt-1 font-medium">{{ $candidato->dados_atualizados_em?->format('d/m/Y') }}</dd></div>
    </dl>
    <div class="flex flex-col gap-2 rounded-lg border border-borda bg-fundo p-4">
        <p class="font-semibold">Retenção até {{ $candidato->retencao_ate?->format('d/m/Y') }}</p>
        @if ($candidato->retencao_vencida)
            <div><x-badge cor="aviso">revisão de retenção pendente</x-badge></div>
        @endif
        <p class="text-sm leading-relaxed text-texto-suave">Prazo de {{ config('banco_talentos.retencao_anos') }} anos após o cadastro ou a última correção. Revise a necessidade de manter os dados; não há exclusão automática. Desativar não equivale a excluir.</p>
    </div>
    <div class="flex flex-wrap items-start gap-3">
        <x-botao :href="route('candidatos.edit', $candidato)" variante="secundario">Editar dados</x-botao>
        <form method="POST" action="{{ route('candidatos.status', $candidato) }}" data-ajax class="flex flex-col gap-2">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $candidato->status === \App\Enums\StatusCandidato::Ativo ? 'desativado' : 'ativo' }}">
            <div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>
            <x-botao type="submit" variante="secundario">{{ $candidato->status === \App\Enums\StatusCandidato::Ativo ? 'Desativar' : 'Reativar' }}</x-botao>
        </form>
        <x-botao href="#exclusao-sem-js" data-abrir-modal="excluir-candidato" variante="perigo">Excluir definitivamente</x-botao>
    </div>
    @if ($errors->hasAny(['motivo', 'confirmacao']))
        <x-alerta tipo="erro">A exclusão não foi realizada. Abra novamente a confirmação e revise os campos indicados.</x-alerta>
    @endif
    <x-modal id="excluir-candidato" titulo="Excluir definitivamente?">
        @include('candidatos.partials._formulario-exclusao', ['contextoExclusao' => 'modal'])
    </x-modal>
    <noscript>
        <section id="exclusao-sem-js" class="rounded-card border border-red-300 p-5" aria-label="Confirmar exclusão definitiva">
            <h3 class="mb-4 font-semibold">Confirmar exclusão definitiva</h3>
            @include('candidatos.partials._formulario-exclusao', ['contextoExclusao' => 'sem-js'])
        </section>
    </noscript>
</section>
