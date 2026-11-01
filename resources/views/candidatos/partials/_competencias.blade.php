{{--
    Competências do candidato com a origem (declarada, revisada ou observada). Usado no perfil do
    banco, no perfil na vaga e no fragmento "competencias-candidato" depois de uma anotação.
    Recebe $competencias (com `pivot->origem`), já autorizadas para o contexto.
--}}
<section aria-labelledby="secao-competencias" class="border-t border-borda pt-6">
    <h2 id="secao-competencias" class="text-xl font-semibold tracking-tight">Competências</h2>
    <p class="mt-2 text-sm leading-relaxed text-texto-suave">A origem distingue o que foi declarado, revisado ou observado em entrevista. Uma declaração não é uma comprovação.</p>
    <div class="mt-5 flex flex-col gap-5">
        @foreach (['hard' => 'Técnicas', 'soft' => 'Comportamentais'] as $categoria => $rotulo)
            <div>
                <h3 class="mb-3 text-sm font-semibold">{{ $rotulo }}</h3>
                <div class="flex flex-wrap gap-2">
                    @forelse ($competencias->filter(fn ($competencia) => $competencia->categoria->value === $categoria) as $competencia)
                        <x-tag-competencia :nome="$competencia->nome" :origem="$competencia->pivot->origem" />
                    @empty
                        <p class="text-texto-suave">Não informado no currículo</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</section>
