<form method="GET" action="{{ $urlBusca }}" data-autosubmit data-busca-candidatos class="flex min-w-0 flex-col gap-5 rounded-card border border-borda bg-superficie p-5" aria-label="Filtrar talentos">
    <div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Filtros</h2>
        <a href="{{ $urlBusca }}" data-limpar-busca class="text-sm font-medium text-primaria-700 underline underline-offset-4">Limpar</a>
    </div>

    {{ $campos['busca'] }}
    {{ $campos['situacao'] }}
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-1">
        {{ $campos['tipo'] }}
        {{ $campos['area'] }}
    </div>
    <label class="flex cursor-pointer items-start gap-3 text-sm leading-relaxed">
        <input type="checkbox" name="experiencia" value="1" {{ $experiencia }} class="mt-1 size-4 shrink-0 accent-primaria-600">
        Somente com experiência na área
    </label>

    <div class="flex flex-col gap-3 border-t border-borda pt-5">
        <div>
            <h2 class="font-semibold">Competências</h2>
            <p id="regra-competencias" class="mt-1 text-sm leading-relaxed text-texto-suave">O perfil precisa ter <strong class="font-semibold text-texto">todas</strong> as selecionadas. Idiomas incluem níveis superiores.</p>
        </div>
        <div data-sem-autoenvio>
            <label for="filtrar-competencias" class="mb-1.5 block text-sm font-medium">Filtrar competências</label>
            <input id="filtrar-competencias" type="search" data-filtrar-chips="#catalogo-competencias" placeholder="Ex.: Python" class="w-full rounded-lg border border-borda-campo px-3 py-2 text-sm">
        </div>
        <div id="catalogo-competencias" class="flex max-h-80 flex-col gap-5 overflow-y-auto p-1" aria-describedby="regra-competencias">
            <fieldset class="min-w-0">
                <legend class="mb-2 text-sm font-semibold text-texto-suave">Técnicas</legend>
                <div class="flex flex-wrap gap-2">
                    {{ $catalogoHtml['hard'] }}
                    <p {{ $catalogoVazio['hard'] }} class="text-sm text-texto-suave">Nenhuma competência no catálogo.</p>
                </div>
            </fieldset>
            <fieldset class="min-w-0">
                <legend class="mb-2 text-sm font-semibold text-texto-suave">Comportamentais</legend>
                <div class="flex flex-wrap gap-2">
                    {{ $catalogoHtml['soft'] }}
                    <p {{ $catalogoVazio['soft'] }} class="text-sm text-texto-suave">Nenhuma competência no catálogo.</p>
                </div>
            </fieldset>
        </div>
    </div>
    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg font-semibold transition-colors duration-150 motion-reduce:transition-none disabled:cursor-not-allowed disabled:opacity-60 bg-primaria-600 text-white shadow-sm hover:bg-primaria-700 px-4 py-2 text-sm">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true" focusable="false" class="size-5 shrink-0 size-4"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
        Aplicar filtros
    </button>
    <noscript><p class="text-sm text-texto-suave">Selecione os critérios e use “Aplicar filtros”.</p></noscript>
</form>
