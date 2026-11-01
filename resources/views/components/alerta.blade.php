@props([
    'tipo' => 'info',
    'titulo' => null,
])

@php
    $estilos = [
        'info' => ['arquivo' => 'help', 'titulo' => 'Informação', 'duracao' => 7000],
        'sucesso' => ['arquivo' => 'success', 'titulo' => 'Tudo certo!', 'duracao' => 7000],
        'aviso' => ['arquivo' => 'warning', 'titulo' => 'Atenção', 'duracao' => 10000],
        'erro' => ['arquivo' => 'fail', 'titulo' => 'Não foi possível concluir', 'duracao' => 10000],
    ];

    $tipo = array_key_exists($tipo, $estilos) ? $tipo : 'info';
    $estilo = $estilos[$tipo];
@endphp

<div data-alerta data-alerta-tipo="{{ $tipo }}" data-alerta-duracao="{{ $estilo['duracao'] }}" role="{{ in_array($tipo, ['erro', 'aviso'], true) ? 'alert' : 'status' }}" aria-atomic="true" {{ $attributes->merge(['class' => 'alerta alerta--'.$tipo]) }}>
    <img src="{{ asset('images/message/'.$estilo['arquivo'].'.svg') }}" alt="" aria-hidden="true" width="438" height="174" class="alerta-arte">
    <div class="relative flex min-w-0 flex-col gap-2">
        <p class="text-base leading-tight font-bold" data-alerta-titulo>{{ $titulo ?? $estilo['titulo'] }}</p>
        <div class="text-sm leading-relaxed wrap-anywhere" data-alerta-mensagem>{{ $slot }}</div>
    </div>
    <button type="button" data-alerta-fechar aria-label="Fechar alerta" title="Fechar alerta" class="alerta-fechar">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" stroke-linecap="round" /></svg>
    </button>
</div>
