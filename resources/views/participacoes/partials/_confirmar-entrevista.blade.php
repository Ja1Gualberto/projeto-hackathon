<dialog id="confirmar-entrevista" aria-labelledby="confirmar-entrevista-titulo" aria-describedby="confirmar-entrevista-descricao" class="m-auto w-full max-w-lg rounded-card border border-borda bg-superficie p-6 text-texto shadow-xl max-sm:max-w-[calc(100%_-_2rem)]">
    <form method="dialog" class="flex flex-col gap-5">
        <h2 id="confirmar-entrevista-titulo" class="text-xl font-semibold">Mover para entrevista?</h2>
        <p id="confirmar-entrevista-descricao" class="leading-relaxed text-texto-suave">O candidato <strong data-confirmacao-codigo class="font-mono text-texto"></strong> sairá da triagem anônima. Sua identidade será revelada nesta vaga e não será possível voltar à triagem.</p>
        <div class="flex flex-wrap justify-end gap-3">
            <x-botao type="submit" value="cancelar" variante="secundario" autofocus>Cancelar</x-botao>
            <x-botao type="submit" value="confirmar">Confirmar entrevista</x-botao>
        </div>
    </form>
</dialog>
