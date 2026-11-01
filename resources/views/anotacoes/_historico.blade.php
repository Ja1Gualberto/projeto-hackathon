{{--
    Histórico de anotações (FR-027), das mais recentes para as mais antigas. Conteúdo da região
    "historico-anotacoes". Recebe $anotacoes com autor, vaga e competências observadas. O texto
    livre é sempre escapado pelo Blade.
--}}
@if ($anotacoes->isEmpty())
    <p class="rounded-lg border border-dashed border-borda px-4 py-6 text-center text-sm text-texto-suave">Nenhuma anotação ainda.</p>
@else
    <ol class="flex flex-col gap-4" aria-label="Anotações, das mais recentes para as mais antigas">
        @foreach ($anotacoes as $anotacao)
            <li>
                <article class="flex flex-col gap-2 rounded-card border border-borda bg-superficie p-4">
                    <p class="flex flex-wrap gap-x-2 text-sm text-texto-suave">
                        <span>Por {{ $anotacao->autor?->name ?? 'usuário removido' }} em {{ $anotacao->created_at->format('d/m/Y H:i') }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ $anotacao->vaga ? 'Vaga: '.$anotacao->vaga->titulo : 'Sem vaga associada' }}</span>
                    </p>
                    <p class="leading-relaxed whitespace-pre-line wrap-anywhere">{{ $anotacao->texto }}</p>
                    @if ($anotacao->competenciasObservadas->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm text-texto-suave">Observadas em entrevista:</span>
                            @foreach ($anotacao->competenciasObservadas as $competencia)
                                <x-tag-competencia :nome="$competencia->nome" :origem="\App\Enums\OrigemCompetencia::Entrevista" />
                            @endforeach
                        </div>
                    @endif
                </article>
            </li>
        @endforeach
    </ol>
@endif
