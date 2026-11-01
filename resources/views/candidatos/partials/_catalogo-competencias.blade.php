{{--
    Conteúdo da região "catalogo-competencias" (contracts/rotas-web.md). A mesma marcação é usada
    na página e no fragmento AJAX, para o cliente mesclar só as opções novas (data-chave) sem
    apagar as seleções feitas na revisão.
--}}
@php
    $selecionadas ??= [];
    $extraidas ??= [];
    $trechos ??= [];
@endphp

<div class="flex flex-col gap-5">
    @foreach (['hard' => 'Técnicas (hard skills)', 'soft' => 'Comportamentais (soft skills)'] as $categoria => $rotulo)
        <fieldset data-grupo="{{ $categoria }}" class="min-w-0">
            <legend class="mb-2 text-sm font-semibold text-texto-suave">{{ $rotulo }}</legend>
            <div data-grupo-itens class="flex flex-wrap gap-2">
                @foreach ($catalogo[$categoria] as $competencia)
                    @php($extraida = in_array($competencia->id, $extraidas, true))
                    <label data-chip data-chave="{{ $competencia->id }}" class="group relative cursor-pointer" @if (isset($trechos[$competencia->id])) title="Encontrada em: {{ $trechos[$competencia->id] }}" @endif>
                        <input type="checkbox" name="competencias[]" value="{{ $competencia->id }}" @checked(in_array($competencia->id, $selecionadas, true)) class="peer sr-only">
                        <span class="inline-flex items-center gap-1.5 rounded-md border border-borda px-2.5 py-2 text-sm leading-tight text-texto-suave peer-checked:border-primaria-600 peer-checked:bg-primaria-50 peer-checked:font-semibold peer-checked:text-primaria-800 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primaria-600 hover:border-primaria-400">
                            <span aria-hidden="true" class="text-primaria-600 group-has-checked:hidden">+</span>
                            <span aria-hidden="true" class="hidden text-primaria-600 group-has-checked:inline">✓</span>
                            {{ $competencia->nome }}
                            @if ($extraida)
                                <span class="text-xs font-normal">· {{ $categoria === 'soft' ? 'declarada no currículo' : 'lida no currículo' }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
            @if ($catalogo[$categoria]->isEmpty())
                <p data-grupo-vazio class="text-sm text-texto-suave">Nenhuma competência nesta categoria.</p>
            @endif
        </fieldset>
    @endforeach
</div>
