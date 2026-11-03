<article data-card-candidato class="group relative flex min-w-0 flex-col gap-2 rounded-card border border-borda bg-superficie p-2.5 transition-[border-color,box-shadow] hover:border-primaria-400 hover:shadow-sm focus-within:border-primaria-600 motion-reduce:transition-none">
    <a href="{{ $urlPerfil }}" class="flex min-w-0 items-start justify-between gap-3 after:absolute after:inset-0 after:rounded-card">
        <div class="flex min-w-0 flex-col gap-1 wrap-anywhere">
            <h3 class="text-base leading-snug font-semibold tracking-tight text-texto group-hover:text-primaria-700">{{ $nome }}</h3>
            <p class="text-xs text-texto-suave">{{ $area }}</p>
            <p class="text-xs text-texto-suave">Experiência na área: {{ $experiencia }}</p>
            <span {{ $atributosUltimaVaga }} class="text-xs text-texto-suave">Última vaga: {{ $ultimaVaga }}</span>
        </div>
        <span class="shrink-0 text-xs text-texto-suave group-hover:text-primaria-700">Ver perfil</span>
    </a>

    <div {{ $atributosDesativado }}>{{ $seloDesativado }}</div>
    <div {{ $atributosRetencao }}>{{ $seloRetencao }}</div>
    <div class="flex min-w-0 flex-col gap-1 border-t border-borda pt-2">
        <div id="{{ $idCompetencias }}" data-competencias-card data-expandido="true" class="group/competencias flex min-w-0 flex-wrap items-start gap-1.5 data-[expandido=false]:flex-nowrap data-[expandido=false]:overflow-hidden">
            {{ $competencias }}
            <p {{ $atributosSemCompetencias }} class="text-xs text-texto-suave">Competências não informadas no currículo</p>
        </div>
        <button type="button" hidden data-expandir-competencias aria-expanded="false" aria-controls="{{ $idCompetencias }}" class="relative z-10 w-fit cursor-pointer rounded px-1 py-1 text-xs font-semibold text-primaria-700 hover:bg-primaria-50 hover:text-primaria-800">Ver todas</button>
    </div>
</article>
