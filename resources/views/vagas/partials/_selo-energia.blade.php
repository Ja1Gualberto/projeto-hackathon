@if ($vaga->temPadraoEnergia())
    <div class="border-t border-borda pt-3 text-sm">
        @if ($item->proximidadeEnergia !== null)
            <x-badge cor="primaria">Energia: {{ $item->proximidadeEnergia }}% de proximidade</x-badge>
        @else
            <span class="text-texto-suave">Energia: Não analisado</span>
        @endif
        <p class="mt-1 text-xs text-texto-suave">Informação complementar; não altera a aderência.</p>
    </div>
@endif
