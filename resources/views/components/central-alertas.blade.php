<section data-alertas class="central-alertas" aria-label="Alertas do sistema" tabindex="-1"></section>

@foreach (['sucesso', 'erro', 'aviso', 'info'] as $tipo)
    <template data-alerta-modelo="{{ $tipo }}"><x-alerta :tipo="$tipo" /></template>
@endforeach
