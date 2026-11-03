<div class="flex flex-wrap items-end justify-between gap-3 border-b border-borda pb-4">
    <div class="flex flex-col gap-1">
        <h2 id="resultados-titulo" tabindex="-1" class="text-xl font-semibold tracking-tight">{{ $total }} {{ $rotuloTotal }}</h2>
        <p class="text-sm text-texto-suave">{{ $situacao }}, em ordem alfabética</p>
    </div>
    <div class="shrink-0">{{ $porPagina }}</div>
</div>

<div {{ $atributosSelecionadas }} role="group" class="flex flex-wrap items-center gap-2" aria-label="Competências selecionadas">
    <span class="text-sm text-texto-suave">Com todas:</span>
    {{ $selecionadas }}
</div>

<div class="grid grid-cols-1 items-start gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
    {{ $cards }}
    <div {{ $atributosVazio }} class="col-span-full">{{ $estadoVazio }}</div>
</div>
<div {{ $atributosPaginacao }} data-paginacao-busca>{{ $paginacao }}</div>
