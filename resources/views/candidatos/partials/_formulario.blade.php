{{--
    Campos do cadastro de um candidato, reutilizados na revisão da importação (US2) e na correção
    de dados (US6). Variáveis: $valores (valores iniciais), $catalogo, $selecionadas,
    $extraidas e $trechos (competências lidas no currículo e onde foram encontradas).
--}}
@php
    $selecionadas = array_map('intval', (array) old('competencias', $selecionadas ?? []));
    $extraidas ??= [];
    $trechos ??= [];
    $opcoes = fn (array $casos): array => collect($casos)->mapWithKeys(fn ($caso) => [$caso->value => $caso->rotulo()])->all();
    $hoje = now()->toDateString();
@endphp

<div class="flex flex-col gap-8">
    <fieldset class="flex flex-col gap-5">
        <legend class="mb-1 text-xl font-semibold tracking-tight">Identificação e contato</legend>
        <p class="-mt-2 text-sm text-texto-suave">Estes dados ficam ocultos na triagem anônima das vagas.</p>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-campo nome="nome" rotulo="Nome completo" :valor="$valores['nome'] ?? null" maxlength="150" autocomplete="off" obrigatorio />
            </div>
            <x-campo nome="email" rotulo="E-mail" tipo="email" :valor="$valores['email'] ?? null" maxlength="150" autocomplete="off" />
            <x-campo nome="telefone" rotulo="Telefone" :valor="$valores['telefone'] ?? null" maxlength="30" autocomplete="off" />
            <x-campo nome="cpf" rotulo="CPF" :valor="$valores['cpf'] ?? null" maxlength="14" inputmode="numeric" autocomplete="off" ajuda="Opcional. Usado para identificar cadastros duplicados." />
            <x-campo nome="data_nascimento" rotulo="Data de nascimento" tipo="date" :valor="$valores['data_nascimento'] ?? null" :max="$hoje" />
        </div>
    </fieldset>

    <fieldset class="flex flex-col gap-5 border-t border-borda pt-6">
        <legend class="mb-1 text-xl font-semibold tracking-tight">Perfil</legend>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-campo nome="tipo" rotulo="Tipo de candidato" tipo="select" :valor="$valores['tipo'] ?? null" vazio="Selecione o tipo" :opcoes="$opcoes(\App\Enums\TipoCandidato::cases())" obrigatorio />
            <x-campo nome="area" rotulo="Área de atuação" tipo="select" :valor="$valores['area'] ?? null" vazio="Selecione a área" :opcoes="$opcoes(\App\Enums\Area::cases())" obrigatorio ajuda="Sugerida pelas competências encontradas. Confira." />
        </div>
        <div class="flex flex-col gap-1">
            <input type="hidden" name="experiencia_na_area" value="0">
            <label class="flex cursor-pointer items-start gap-3 leading-relaxed">
                <input type="checkbox" name="experiencia_na_area" value="1" @checked((bool) old('experiencia_na_area', $valores['experiencia_na_area'] ?? false)) class="mt-1 size-4 shrink-0 accent-primaria-600">
                <span>Tem experiência na área informada</span>
            </label>
            <p class="pl-7 text-sm text-texto-suave">Desmarcado significa “não informado no currículo”, e não “não possui”.</p>
        </div>
    </fieldset>

    <fieldset class="flex flex-col gap-5 border-t border-borda pt-6">
        <legend class="mb-1 text-xl font-semibold tracking-tight">Trajetória</legend>
        <p class="-mt-2 text-sm text-texto-suave">Um item por linha. Linhas vazias são ignoradas.</p>
        <x-campo nome="formacoes" rotulo="Formação" tipo="textarea" :valor="$valores['formacoes'] ?? []" />
        <x-campo nome="experiencias" rotulo="Experiências" tipo="textarea" :valor="$valores['experiencias'] ?? []" />
        <x-campo nome="projetos" rotulo="Projetos" tipo="textarea" :valor="$valores['projetos'] ?? []" />
        <x-campo nome="links" rotulo="Links (portfólio, GitHub, LinkedIn)" tipo="textarea" :valor="$valores['links'] ?? []" ajuda="Somente endereços completos, começando com http:// ou https://." />
    </fieldset>

    <fieldset class="flex flex-col gap-4 border-t border-borda pt-6">
        <legend class="mb-1 text-xl font-semibold tracking-tight">Competências</legend>
        <p class="-mt-1 text-sm leading-relaxed text-texto-suave">
            Marque o que o candidato informou. Competências comportamentais lidas no currículo são
            <strong class="font-semibold text-texto">declaradas</strong>, não comprovadas.
        </p>
        <div data-sem-autoenvio>
            <label for="filtrar-competencias" class="mb-1.5 block text-sm font-medium">Filtrar competências</label>
            <input id="filtrar-competencias" type="search" data-filtrar-chips="#catalogo-competencias" placeholder="Ex.: Python" class="w-full max-w-sm rounded-lg border border-borda px-3 py-2 text-sm">
        </div>
        <div id="catalogo-competencias" class="max-h-96 overflow-y-auto p-1">
            @include('candidatos.partials._catalogo-competencias')
        </div>
        <p id="erro-competencias" data-erro-campo="competencias" @if (! $errors->has('competencias') && ! $errors->has('competencias.*')) hidden @endif class="text-sm font-medium text-red-700">
            {{ $errors->first('competencias') ?: $errors->first('competencias.*') }}
        </p>
        @if ($trechos !== [])
            <details class="rounded-lg border border-borda bg-fundo p-4 text-sm">
                <summary class="cursor-pointer font-semibold">Onde cada competência foi encontrada no currículo</summary>
                <dl class="mt-3 flex flex-col gap-3">
                    @foreach ($catalogo['hard']->concat($catalogo['soft'])->filter(fn ($competencia) => isset($trechos[$competencia->id])) as $competencia)
                        <div>
                            <dt class="font-semibold">{{ $competencia->nome }}</dt>
                            <dd class="wrap-anywhere text-texto-suave">“{{ $trechos[$competencia->id] }}”</dd>
                        </div>
                    @endforeach
                </dl>
            </details>
        @endif
    </fieldset>

    <fieldset class="flex flex-col gap-5 rounded-card border border-primaria-200 bg-primaria-50 p-5">
        <legend class="px-1 text-xl font-semibold tracking-tight">Consentimento do candidato</legend>
        <p class="text-sm leading-relaxed">
            A LGPD exige o consentimento do candidato para o uso dos dados. Registre como e quando ele
            foi obtido (ex.: e-mail, formulário ou declaração do CIEE). O envio do arquivo por outra
            pessoa não substitui esse consentimento.
        </p>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-campo nome="consentimento_meio" rotulo="Meio do consentimento" tipo="select" :valor="$valores['consentimento_meio'] ?? null" vazio="Selecione como foi obtido" :opcoes="$opcoes(\App\Enums\MeioConsentimento::cases())" obrigatorio />
            <x-campo nome="consentimento_em" rotulo="Data do consentimento" tipo="date" :valor="$valores['consentimento_em'] ?? null" :max="$hoje" obrigatorio />
        </div>
    </fieldset>
</div>
