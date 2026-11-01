@props(['eixos', 'vaga' => null])

<div {{ $attributes->class(['flex flex-col gap-5']) }}>
    @foreach (['dinamico_analitico' => ['Dinâmico', 'Analítico'], 'individual_equipe' => ['Individual', 'Equipe'], 'execucao_planejamento' => ['Execução', 'Planejamento']] as $eixo => [$inicio, $fim])
        <div class="flex flex-col gap-2">
            <div class="flex justify-between gap-3 text-sm font-medium"><span>{{ $inicio }}</span><span>{{ $fim }}</span></div>
            <ol aria-label="{{ $inicio }} a {{ $fim }}: candidato {{ $eixos[$eixo] }} de 5{{ isset($vaga[$eixo]) ? '; vaga '.$vaga[$eixo].' de 5' : '' }}" class="grid grid-cols-5 gap-2">
                @for ($posicao = 1; $posicao <= 5; $posicao++)
                    <li @class(['flex min-h-9 items-center justify-center rounded-md border text-sm font-semibold', 'border-primaria-700 bg-primaria-700 text-white' => (int) $eixos[$eixo] === $posicao, 'border-borda bg-slate-50 text-texto-suave' => (int) $eixos[$eixo] !== $posicao, 'ring-2 ring-amber-600 ring-offset-2' => isset($vaga[$eixo]) && (int) $vaga[$eixo] === $posicao]) aria-hidden="true">{{ $posicao }}</li>
                @endfor
            </ol>
        </div>
    @endforeach
    <p class="text-sm text-texto-suave">Preenchido: candidato.@if ($vaga !== null) Contorno: desejado na vaga.@endif Escala de 1 a 5; 3 é neutro.</p>
</div>
