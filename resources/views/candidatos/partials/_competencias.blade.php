{{--
    Competências do candidato com a origem (declarada, revisada ou observada). Usado no perfil do
    banco, no perfil na vaga e no fragmento "competencias-candidato" depois de uma anotação.
    Recebe $competencias (com `pivot->origem`), já autorizadas para o contexto.
--}}
<section aria-labelledby="{{ $idSecao ?? 'secao-competencias' }}" class="flex flex-col gap-3">
    <h2 id="{{ $idSecao ?? 'secao-competencias' }}" class="text-base font-semibold">Competências</h2>
    {{-- <p class="max-w-prose text-sm leading-relaxed text-texto-suave">A origem distingue o que foi declarado, revisado ou observado em entrevista. Uma declaração não é uma comprovação.</p> --}}
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach (['hard' => 'Técnicas', 'soft' => 'Comportamentais'] as $categoria => $rotulo)
            <div>
                <h3 class="mb-2 text-sm font-medium">{{ $rotulo }}</h3>
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
