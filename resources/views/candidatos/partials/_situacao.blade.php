<div class="flex flex-wrap items-center gap-3" tabindex="-1" data-foco-apos-ajax>
    <x-badge :cor="$candidato->status === \App\Enums\StatusCandidato::Ativo ? 'sucesso' : 'neutro'">{{ $candidato->status->rotulo() }}</x-badge>
    @if ($candidato->status === \App\Enums\StatusCandidato::Desativado)
        <p class="text-sm text-texto-suave">Fora das buscas e triagens. Os dados continuam guardados.</p>
    @endif
</div>
