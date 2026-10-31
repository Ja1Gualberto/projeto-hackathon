<a href="{{ route('candidatos.show', $candidato) }}" class="group flex h-full min-w-0 flex-col gap-4 rounded-card border border-borda bg-superficie p-5 transition-colors hover:border-primaria-400 focus-visible:border-primaria-600">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-badge-tipo :tipo="$candidato->tipo" class="normal-case tracking-normal" />
        <span class="text-sm text-texto-suave group-hover:text-primaria-700">Ver perfil</span>
    </div>
    <div class="flex flex-col gap-1">
        <h3 class="text-xl leading-snug font-semibold tracking-tight text-texto group-hover:text-primaria-700">{{ $candidato->nome }}</h3>
        <p class="text-sm text-texto-suave">{{ $candidato->area->rotulo() }}</p>
    </div>
    <p class="text-sm text-texto-suave">Experiência na área: {{ $candidato->experiencia_na_area ? 'sim' : 'não informado' }}</p>
    <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-borda pt-4">
        @forelse ($candidato->competencias->take(5) as $competencia)
            <x-tag-competencia :nome="$competencia->nome" />
        @empty
            <p class="text-sm text-texto-suave">Competências não informadas no currículo</p>
        @endforelse
        @if ($candidato->competencias->count() > 5)
            <span class="text-sm text-texto-suave" aria-label="Mais {{ $candidato->competencias->count() - 5 }} competências">+{{ $candidato->competencias->count() - 5 }}</span>
        @endif
    </div>
</a>
