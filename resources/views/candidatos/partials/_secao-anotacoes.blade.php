{{-- Anotações de entrevista na gestão do banco (US5): vaga opcional e competências observadas. --}}
<section class="flex flex-col gap-4 border-t border-borda pt-6" aria-labelledby="secao-anotacoes">
    <div>
        <h2 id="secao-anotacoes" class="text-xl font-semibold tracking-tight">Anotações de entrevista</h2>
        <p class="mt-2 text-sm leading-relaxed text-texto-suave">Registre o que foi percebido na conversa para não se perder entre seleções. A vaga é opcional.</p>
    </div>

    <div>
        <x-botao variante="secundario" data-abrir-modal="anotacoes-entrevista" aria-haspopup="dialog" aria-controls="anotacoes-entrevista">Ver anotações de entrevista</x-botao>
    </div>

    @include('anotacoes._modal', [
        'anotacoes' => $anotacoes,
        'acao' => route('candidatos.anotacoes.store', $candidato),
        'descricao' => 'Anotações das entrevistas com '.$candidato->nome.'. A vaga é opcional ao registrar uma nova anotação.',
    ])
</section>
