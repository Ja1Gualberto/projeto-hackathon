<a href="{{ $urlPerfil }}" class="group flex h-full min-w-0 flex-col gap-1 rounded-card border border-borda bg-superficie p-3 transition-colors hover:border-primaria-400 focus-visible:border-primaria-600">
    <div class="flex flex-wrap items-center justify-between gap-3">
        {{ $tipo }}
        <span class="text-sm text-texto-suave group-hover:text-primaria-700">Ver perfil</span>
    </div>
    <div class="flex flex-col gap-1">
        <h3 class="text-xl leading-snug font-semibold tracking-tight text-texto group-hover:text-primaria-700">{{ $nome }}</h3>
        <p class="text-sm text-texto-suave">{{ $area }}</p>
    </div>

    <p class="text-sm text-texto-suave">Experiência na área: {{ $experiencia }}</p>

    <div {{ $atributosDesativado }}>{{ $seloDesativado }}</div>
    <div {{ $atributosRetencao }}>{{ $seloRetencao }}</div>
    <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-borda pt-4">
        {{ $competencias }}
        <p {{ $atributosSemCompetencias }} class="text-sm text-texto-suave">Competências não informadas no currículo</p>
        <span {{ $atributosExcedentes }} class="text-sm text-texto-suave" aria-label="Mais {{ $excedentes }} competências">+{{ $excedentes }}</span>
    </div>
</a>
