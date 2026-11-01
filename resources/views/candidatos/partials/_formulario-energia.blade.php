<form method="POST" action="{{ route('candidatos.energia.update', $candidato) }}" data-ajax class="flex flex-col gap-4">
    @csrf
    @method('PUT')
    <div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>
    @if ($errors->any())
        <x-alerta tipo="erro" titulo="Revise os campos indicados.">
            @foreach ($errors->all() as $erro)<p>{{ $erro }}</p>@endforeach
        </x-alerta>
    @endif
    @foreach (['dinamico_analitico' => 'Dinâmico ↔ Analítico', 'individual_equipe' => 'Individual ↔ Equipe', 'execucao_planejamento' => 'Execução ↔ Planejamento'] as $eixo => $rotulo)
        <x-campo :id="'energia-'.$sufixo.'-'.$eixo" :nome="$eixo" :rotulo="$rotulo" tipo="select" :valor="$energia?->$eixo ?? 3" :opcoes="[1 => '1', 2 => '2', 3 => '3 · Neutro', 4 => '4', 5 => '5']" obrigatorio />
    @endforeach
    <x-campo :id="'energia-'.$sufixo.'-justificativa'" nome="justificativa" rotulo="Justificativa" tipo="textarea" :valor="$energia?->justificativa" maxlength="500" ajuda="Opcional, até 500 caracteres. Registre evidências, não diagnósticos." />
    <x-botao type="submit">Salvar ajuste manual</x-botao>
</form>
