{{-- Anotações de entrevista na gestão do banco (US5): vaga opcional e competências observadas. --}}
<section class="flex flex-col gap-4 border-t border-borda pt-6" aria-labelledby="secao-anotacoes">
    <div>
        <h2 id="secao-anotacoes" class="text-xl font-semibold tracking-tight">Anotações de entrevista</h2>
        <p class="mt-2 text-sm leading-relaxed text-texto-suave">Registre o que foi percebido na conversa para não se perder entre seleções. A vaga é opcional.</p>
    </div>

    @include('anotacoes._formulario', ['acao' => route('candidatos.anotacoes.store', $candidato), 'vagas' => $vagas, 'catalogo' => $catalogo])

    <div id="historico-anotacoes">
        @include('anotacoes._historico', ['anotacoes' => $anotacoes])
    </div>
</section>
