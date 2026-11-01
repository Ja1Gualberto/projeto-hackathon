<form method="POST" action="{{ route('candidatos.destroy', $candidato) }}" data-ajax class="flex flex-col gap-5">
    @csrf
    @method('DELETE')
    <p class="text-sm leading-relaxed">Esta ação é irreversível. Serão apagados os dados pessoais, o PDF, as competências do candidato, as participações, as movimentações, as anotações e o perfil de energia. Somente data, responsável e motivo da exclusão serão registrados, sem identificar o candidato.</p>
    <div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>
    <x-campo nome="motivo" :id="'motivo-exclusao-'.$contextoExclusao" rotulo="Motivo da exclusão" tipo="select" vazio="Selecione o motivo" :opcoes="collect(\App\Enums\MotivoExclusao::cases())->mapWithKeys(fn ($motivo) => [$motivo->value => $motivo->rotulo()])->all()" obrigatorio />
    <div class="flex flex-col gap-2">
        <label class="flex cursor-pointer items-start gap-3 text-sm leading-relaxed">
            <input type="checkbox" name="confirmacao" value="1" required class="mt-1 size-4 shrink-0 accent-red-700" @if ($errors->has('confirmacao')) aria-invalid="true" aria-describedby="confirmacao-erro-{{ $contextoExclusao }}" @endif>
            Confirmo a exclusão definitiva dos dados deste candidato.
        </label>
        <p id="confirmacao-erro-{{ $contextoExclusao }}" data-erro-campo="confirmacao" @if (! $errors->has('confirmacao')) hidden @endif class="text-sm font-medium text-red-700">{{ $errors->first('confirmacao') }}</p>
    </div>
    <x-botao type="submit" variante="perigo">Excluir definitivamente</x-botao>
</form>
