<div class="flex flex-wrap items-baseline justify-between gap-3 border-b border-borda pb-4">
    <h2 id="resultados-titulo" tabindex="-1" class="text-xl font-semibold tracking-tight">{{ $total }} {{ $rotuloTotal }}</h2>
    <p class="text-sm text-texto-suave">{{ $situacao }}, em ordem alfabética</p>
</div>

<div {{ $atributosSelecionadas }} role="group" class="flex flex-wrap items-center gap-2" aria-label="Competências selecionadas">
    <span class="text-sm text-texto-suave">Com todas:</span>
    {{ $selecionadas }}
</div>

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    {{ $cards }}
    <div {{ $atributosVazio }} class="col-span-full">{{ $estadoVazio }}</div>
</div>
<div {{ $atributosPaginacao }} data-paginacao-busca>{{ $paginacao }}</div>
