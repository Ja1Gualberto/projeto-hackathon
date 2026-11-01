{{--
    Campos da vaga, usados na criação e na edição. Cada competência do catálogo tem um select de
    peso `competencias[ID][peso]`; "Não usar" deixa a competência fora da vaga. O id no nome faz
    cada erro de validação (`competencias.ID.peso`) aparecer junto ao select certo, também em AJAX.
--}}
@php
    // Depois de um erro de validação, os selects mostram exatamente o que foi enviado.
    $usarEnviados = session()->hasOldInput();
@endphp

<div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>

@if ($errors->any())
    <x-alerta tipo="erro" titulo="Revise os campos indicados para salvar a vaga.">
        <ul class="list-inside list-disc">
            @foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach
        </ul>
    </x-alerta>
@endif

<section aria-labelledby="dados-vaga-titulo" class="flex flex-col gap-5">
    <h2 id="dados-vaga-titulo" class="text-lg font-semibold">Dados da vaga</h2>
    <x-campo nome="titulo" rotulo="Título" :valor="$vaga->titulo" maxlength="120" obrigatorio placeholder="Ex.: Estagiário de Desenvolvimento" />
    <div class="grid gap-5 sm:grid-cols-2">
        <x-campo nome="area" rotulo="Área" tipo="select" :valor="$vaga->area" vazio="Selecione a área" obrigatorio
            :opcoes="collect(\App\Enums\Area::cases())->mapWithKeys(fn ($area) => [$area->value => $area->rotulo()])->all()" />
        <x-campo nome="tipo_candidato" rotulo="Tipo de candidato" tipo="select" :valor="$vaga->tipo_candidato" vazio="Selecione quem pode participar" obrigatorio
            :opcoes="collect(\App\Enums\TipoCandidatoVaga::cases())->mapWithKeys(fn ($tipo) => [$tipo->value => $tipo->rotulo()])->all()" />
    </div>
    <x-campo nome="descricao" rotulo="Descrição" tipo="textarea" :valor="$vaga->descricao" maxlength="1000" ajuda="Opcional, até 1000 caracteres." />
</section>

<section aria-labelledby="competencias-vaga-titulo" class="flex flex-col gap-5 border-t border-borda pt-6">
    <div class="flex flex-col gap-1">
        <h2 id="competencias-vaga-titulo" class="text-lg font-semibold">
            Competências e pesos <span class="text-red-700" aria-hidden="true">*</span>
        </h2>
        <p id="regra-pesos" class="max-w-3xl text-sm leading-relaxed text-texto-suave">
            Dê um peso a cada competência pedida: <strong class="font-semibold text-texto">1 desejável</strong>,
            <strong class="font-semibold text-texto">2 importante</strong> ou <strong class="font-semibold text-texto">3 essencial</strong>.
            A aderência é a soma dos pesos que o candidato atende dividida pela soma de todos. Idiomas aceitam níveis superiores.
        </p>
        @error('competencias')
            <p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
        @enderror
    </div>

    <div class="max-w-sm">
        <label for="filtrar-competencias-vaga" class="mb-1.5 block text-sm font-medium">Filtrar competências</label>
        <input id="filtrar-competencias-vaga" type="search" data-filtrar-chips="#competencias-vaga" placeholder="Ex.: Python" class="w-full rounded-lg border border-borda px-3 py-2 text-sm">
    </div>

    <div id="competencias-vaga" class="flex flex-col gap-6">
        @foreach (['hard' => 'Competências técnicas (hard skills)', 'soft' => 'Competências comportamentais (soft skills)'] as $categoria => $rotulo)
            <fieldset class="min-w-0" aria-describedby="regra-pesos">
                <legend class="mb-3 font-semibold">{{ $rotulo }}</legend>
                <div class="grid gap-x-5 gap-y-2 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($catalogo[$categoria] as $competencia)
                        @php
                            $chave = "competencias.{$competencia->id}.peso";
                            $idCampo = "peso-competencia-{$competencia->id}";
                            $pesoAtual = (string) ($usarEnviados ? old($chave) : ($pesos[$competencia->id] ?? ''));
                            $erro = $errors->first($chave) ?: $errors->first("competencias.{$competencia->id}.id");
                        @endphp
                        <div data-chip data-chip-texto="{{ $competencia->nome }}" class="flex min-w-0 flex-col gap-1">
                            {{-- A linha fica destacada enquanto um peso estiver escolhido, também sem recarregar. --}}
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-borda px-3 py-2 has-[option:checked:not([value=''])]:border-primaria-600 has-[option:checked:not([value=''])]:bg-primaria-50">
                                <label for="{{ $idCampo }}" class="min-w-0 text-sm font-medium wrap-anywhere">{{ $competencia->nome }}</label>
                                <select id="{{ $idCampo }}" name="competencias[{{ $competencia->id }}][peso]" @if ($erro) aria-invalid="true" aria-describedby="{{ $idCampo }}-erro" @endif class="shrink-0 rounded-md border border-borda bg-superficie py-1 pr-7 pl-2 text-sm focus:border-primaria-500 focus:ring-2 focus:ring-primaria-200 focus:outline-none">
                                    <option value="">Não usar</option>
                                    @foreach (\App\Enums\PesoCompetencia::cases() as $peso)
                                        <option value="{{ $peso->value }}" @selected($pesoAtual === (string) $peso->value)>{{ $peso->value }} · {{ $peso->rotulo() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p id="{{ $idCampo }}-erro" data-erro-campo="{{ $chave }}" @if (! $erro) hidden @endif class="text-sm font-medium text-red-700">{{ $erro }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-texto-suave">Nenhuma competência nesta categoria.</p>
                    @endforelse
                </div>
            </fieldset>
        @endforeach
    </div>
</section>

{{-- Padrão de energia da vaga (US7, T139): eixos opcionais, incluídos aqui pela trilha B. --}}
