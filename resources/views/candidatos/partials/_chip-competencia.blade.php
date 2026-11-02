<label data-chip class="group/chip relative cursor-pointer">
    <input type="checkbox" name="competencias[]" value="{{ $id }}" {{ $marcado }} class="peer sr-only">
    <span class="inline-flex items-center gap-1.5 rounded-md border border-borda px-2.5 py-2 text-sm leading-tight text-texto-suave peer-checked:border-primaria-600 peer-checked:bg-primaria-50 peer-checked:font-semibold peer-checked:text-primaria-800 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primaria-600 hover:border-primaria-400">
        <span aria-hidden="true" class="text-primaria-600 group-has-checked/chip:hidden">+</span>
        <span aria-hidden="true" class="hidden text-primaria-600 group-has-checked/chip:inline">✓</span>
        {{ $nome }}
    </span>
</label>
