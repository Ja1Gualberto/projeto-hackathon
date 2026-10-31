@props([
    'nome',
    'rotulo',
    'tipo' => 'text',
    'valor' => null,
    'opcoes' => [],
    'vazio' => null,
    'ajuda' => null,
    'obrigatorio' => false,
])

@php
    $id = $attributes->get('id', 'campo-'.trim(preg_replace('/[^a-z0-9]+/i', '-', $nome), '-'));
    $chaveErro = trim(str_replace(['[]', '[', ']'], ['', '.', ''], $nome), '.');
    $valorAtual = old($chaveErro, $valor);
    $valorAtual = $valorAtual instanceof \BackedEnum ? $valorAtual->value : $valorAtual;
    $temErro = isset($errors) && $errors->has($chaveErro);

    $classesCampo = 'block w-full rounded-lg border bg-superficie px-3 py-2 text-texto shadow-xs placeholder:text-slate-400 focus:border-primaria-500 focus:ring-2 focus:ring-primaria-200 focus:outline-none '
        .($temErro ? 'border-red-600' : 'border-borda');

    $atributosCampo = $attributes->except('id')->merge(['class' => $classesCampo]);
    $descricao = trim(($ajuda ? $id.'-ajuda ' : '').($temErro ? $id.'-erro' : ''));
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-sm font-semibold text-texto">
        {{ $rotulo }}
        @if ($obrigatorio)
            <span class="text-red-700" aria-hidden="true">*</span>
        @endif
    </label>

    @if ($tipo === 'select')
        <select id="{{ $id }}" name="{{ $nome }}" @required($obrigatorio) @if ($temErro) aria-invalid="true" @endif @if ($descricao) aria-describedby="{{ $descricao }}" @endif {{ $atributosCampo }}>
            @if ($vazio !== null)
                <option value="">{{ $vazio }}</option>
            @endif
            @foreach ($opcoes as $valorOpcao => $rotuloOpcao)
                <option value="{{ $valorOpcao }}" @selected((string) $valorAtual === (string) $valorOpcao)>{{ $rotuloOpcao }}</option>
            @endforeach
        </select>
    @elseif ($tipo === 'textarea')
        <textarea id="{{ $id }}" name="{{ $nome }}" rows="4" @required($obrigatorio) @if ($temErro) aria-invalid="true" @endif @if ($descricao) aria-describedby="{{ $descricao }}" @endif {{ $atributosCampo }}>{{ $valorAtual }}</textarea>
    @else
        <input
            type="{{ $tipo }}"
            id="{{ $id }}"
            name="{{ $nome }}"
            @if (! in_array($tipo, ['password', 'file'], true)) value="{{ $valorAtual }}" @endif
            @required($obrigatorio)
            @if ($temErro) aria-invalid="true" @endif
            @if ($descricao) aria-describedby="{{ $descricao }}" @endif
            {{ $atributosCampo }}
        >
    @endif

    @if ($ajuda)
        <p id="{{ $id }}-ajuda" class="text-sm text-texto-suave">{{ $ajuda }}</p>
    @endif

    @if ($temErro)
        <p id="{{ $id }}-erro" class="text-sm font-medium text-red-700">{{ $errors->first($chaveErro) }}</p>
    @endif
</div>
