@php
    $erroAnotacao = $errors->hasAny(['texto', 'vaga_id', 'competencias_observadas', 'competencias_observadas.*']);
@endphp

<section aria-labelledby="entrevistas-titulo" class="flex flex-col gap-4 border-b border-borda pb-5">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex min-w-0 flex-col gap-1">
            <h2 id="entrevistas-titulo" class="text-base font-semibold">Anotações de entrevista</h2>
            <p class="max-w-prose text-sm leading-relaxed text-texto-suave">As três anotações mais recentes do candidato, de todas as vagas.</p>
        </div>
        <x-botao data-abrir-modal="nova-anotacao" aria-haspopup="dialog" aria-controls="nova-anotacao" class="max-w-full shrink-0 self-end sm:self-start">
            <span aria-hidden="true">+</span>Adicionar nova anotação
        </x-botao>
    </header>

    <div id="historico-anotacoes">
        @include('anotacoes._historico', ['anotacoes' => $anotacoes->take(3)])
    </div>

    <x-modal id="nova-anotacao" titulo="Adicionar nova anotação" amplo :abrir-ao-carregar="$erroAnotacao" class="max-h-[calc(100dvh-2rem)] overflow-y-auto max-sm:max-w-[calc(100%_-_2rem)]">
        @include('anotacoes._formulario', [
            'acao' => route('candidatos.anotacoes.store', $candidato),
            'catalogo' => $catalogo,
        ])
    </x-modal>

    <noscript>
        <style>
            #nova-anotacao { display: block; position: static; max-height: none; }
            #entrevistas [data-abrir-modal="nova-anotacao"], #nova-anotacao [data-fechar-modal] { display: none; }
        </style>
    </noscript>
</section>
