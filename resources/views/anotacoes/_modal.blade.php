@php
    $erroAnotacao = $errors->hasAny(['texto', 'vaga_id', 'competencias_observadas', 'competencias_observadas.*']);
@endphp

<x-modal id="anotacoes-entrevista" titulo="Anotações de entrevista" amplo :abrir-ao-carregar="$erroAnotacao" aria-describedby="anotacoes-descricao" class="max-h-[calc(100dvh-2rem)] overflow-y-auto">
    <x-slot:acoes>
        @if ($acao !== null)
            <x-botao data-alternar-regiao="nova-anotacao" aria-controls="nova-anotacao" :aria-expanded="$erroAnotacao ? 'true' : 'false'">
                <span aria-hidden="true">+</span>Adicionar nova anotação
            </x-botao>
        @endif
    </x-slot:acoes>

    <div class="flex flex-col gap-5">
        <p id="anotacoes-descricao" class="text-sm leading-relaxed text-texto-suave">{{ $descricao }}</p>

        <div id="historico-anotacoes">
            @include('anotacoes._historico', ['anotacoes' => $anotacoes])
        </div>

        @if ($acao !== null)
            <div id="nova-anotacao" data-regiao-recolhivel @if (! $erroAnotacao)hidden @endif class="border-t border-borda pt-5">
                @include('anotacoes._formulario', ['acao' => $acao, 'catalogo' => $catalogo])
            </div>
        @endif
    </div>
</x-modal>

<noscript>
    <style>
        #anotacoes-entrevista { display: block; position: static; max-height: none; }
        #nova-anotacao { display: block; }
        [data-abrir-modal="anotacoes-entrevista"], #anotacoes-entrevista [data-fechar-modal], #anotacoes-entrevista [data-alternar-regiao] { display: none; }
    </style>
</noscript>
