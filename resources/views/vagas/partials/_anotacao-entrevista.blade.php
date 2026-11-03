<dialog id="anotar-entrevista-quadro" aria-labelledby="anotar-entrevista-titulo" aria-describedby="anotar-entrevista-descricao" class="m-auto max-h-[calc(100dvh_-_2rem)] w-full max-w-xl overflow-y-auto rounded-card border border-borda bg-superficie p-6 text-texto shadow-xl max-sm:max-w-[calc(100%_-_2rem)]">
    <div class="flex flex-col gap-5">
        <header class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-2">
                <h2 id="anotar-entrevista-titulo" class="text-xl font-semibold">Anotação da entrevista</h2>
                <p data-anotacao-candidato class="font-semibold wrap-anywhere"></p>
            </div>
            <x-botao variante="fantasma" data-fechar-modal aria-label="Fechar anotação"><x-icone nome="x" /></x-botao>
        </header>
        <p id="anotar-entrevista-descricao" class="text-sm text-texto-suave">Registre o que foi observado e escolha a próxima etapa. A anotação e a decisão serão salvas juntas, com autor e data.</p>
        <form method="POST" data-anotar-entrevista class="flex flex-col gap-4">
            @csrf
            <input type="hidden" name="contexto" value="quadro">
            <div data-ajax-erros role="alert" tabindex="-1" hidden></div>
            <div class="flex flex-col gap-2">
                <label for="anotacao-entrevista-texto" class="text-sm font-semibold">Anotação <span class="text-texto-suave">(obrigatório)</span></label>
                <textarea id="anotacao-entrevista-texto" name="texto" required maxlength="5000" rows="5" aria-describedby="anotacao-entrevista-ajuda" class="w-full rounded-lg border border-borda-campo bg-superficie px-3 py-2 text-texto" placeholder="O que foi observado na entrevista?"></textarea>
                <p id="anotacao-entrevista-ajuda" class="text-sm text-texto-suave">Até 5000 caracteres.</p>
            </div>
            <div class="flex flex-col gap-2">
                <label for="anotacao-entrevista-etapa" class="text-sm font-semibold">Ao salvar a anotação</label>
                <select id="anotacao-entrevista-etapa" name="etapa" class="w-full rounded-lg border border-borda-campo bg-superficie px-3 py-2 text-texto">
                    <option value="">Manter em Entrevista</option>
                    <option value="aprovado">Mover para Aprovado</option>
                    <option value="reprovado">Mover para Reprovado</option>
                </select>
            </div>
            <div class="flex flex-wrap justify-end gap-3">
                <x-botao variante="secundario" data-fechar-modal>Cancelar</x-botao>
                <x-botao type="submit">Salvar anotação</x-botao>
            </div>
        </form>
    </div>
</dialog>
