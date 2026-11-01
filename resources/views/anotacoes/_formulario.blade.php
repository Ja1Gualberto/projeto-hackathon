{{--
    Registro de anotação de entrevista (FR-026). Fica fora das regiões atualizadas: com JavaScript,
    o histórico e as competências são trocados e o formulário só é limpo depois do sucesso
    (data-limpar-apos-sucesso); em erro, o rascunho fica. Parâmetros: $acao (URL), $catalogo
    (hard/soft) e, só na gestão do banco, $vagas para o select opcional.
--}}
@php
    $observadas = array_map('strval', (array) old('competencias_observadas', []));
@endphp

<form method="POST" action="{{ $acao }}" data-ajax data-limpar-apos-sucesso class="flex flex-col gap-4 rounded-card border border-borda bg-superficie p-5" aria-label="Registrar anotação de entrevista">
    @csrf
    <div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>

    <x-campo nome="texto" rotulo="Anotação" tipo="textarea" maxlength="5000" obrigatorio data-foco-apos-limpar
        placeholder="O que foi observado na entrevista?" ajuda="Até 5000 caracteres. Autor e data são registrados automaticamente." />

    @isset($vagas)
        <x-campo nome="vaga_id" rotulo="Vaga (opcional)" tipo="select" vazio="Sem vaga associada"
            :opcoes="$vagas->mapWithKeys(fn ($vaga) => [$vaga->id => $vaga->titulo.($vaga->estaAberta() ? '' : ' (encerrada)')])->all()" />
    @endisset

    <fieldset class="flex min-w-0 flex-col gap-3">
        <legend class="text-sm font-semibold">Competências observadas (opcional)</legend>
        <p class="text-sm text-texto-suave">Passam a constar no perfil como "observada em entrevista".</p>
        <div class="max-w-sm">
            <label for="filtrar-observadas" class="sr-only">Filtrar competências observadas</label>
            <input id="filtrar-observadas" type="search" data-filtrar-chips="#competencias-observadas" placeholder="Filtrar competências" class="w-full rounded-lg border border-borda px-3 py-2 text-sm">
        </div>
        <div id="competencias-observadas" class="flex max-h-56 flex-col gap-4 overflow-y-auto p-1">
            @foreach (['soft' => 'Comportamentais', 'hard' => 'Técnicas'] as $categoria => $rotulo)
                <div>
                    <p class="mb-2 text-sm font-semibold text-texto-suave">{{ $rotulo }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($catalogo[$categoria] as $competencia)
                            <label data-chip class="group relative cursor-pointer">
                                <input type="checkbox" name="competencias_observadas[]" value="{{ $competencia->id }}" @checked(in_array((string) $competencia->id, $observadas, true)) class="peer sr-only">
                                <span class="inline-flex items-center gap-1.5 rounded-md border border-borda px-2.5 py-1.5 text-sm leading-tight text-texto-suave peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:font-semibold peer-checked:text-emerald-800 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primaria-600 hover:border-primaria-400">
                                    <span aria-hidden="true" class="group-has-checked:hidden">+</span>
                                    <span aria-hidden="true" class="hidden group-has-checked:inline">✓</span>
                                    {{ $competencia->nome }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </fieldset>

    <div class="flex justify-end">
        <x-botao type="submit"><x-icone nome="check" class="size-4" />Registrar anotação</x-botao>
    </div>
</form>
