/**
 * Revisão da importação de currículo (US2): "Ignorar" retira a sugestão da lista sem chamar o
 * servidor; incluir uma sugestão no catálogo (formulário data-sugestao-form, mesclado por
 * formularios.js) retira a sugestão e leva o foco à nova opção, já marcada. No envio, mostra os
 * arquivos escolhidos e recusa no navegador o que passa do limite por envio.
 */
function focarCompetencia(doc, chave) {
    const regiao = doc.getElementById('catalogo-competencias');
    const item = Array.from(regiao?.querySelectorAll('[data-chave]') ?? []).find((elemento) => elemento.dataset.chave === chave);
    item?.querySelector('input')?.focus();
}

export function mostrarArquivosSelecionados(campo, doc = campo.ownerDocument) {
    const arquivos = Array.from(campo.files ?? []);
    const limite = Number(campo.dataset.maxArquivos);
    const lista = doc.querySelector('[data-arquivos-lista]');

    campo.setCustomValidity(arquivos.length > limite ? `Envie no máximo ${limite} currículos por vez.` : '');

    if (lista) {
        lista.replaceChildren(...arquivos.map((arquivo) => {
            const item = doc.createElement('li');
            item.textContent = arquivo.name;
            return item;
        }));
        lista.hidden = arquivos.length === 0;
    }
}

export function registrarImportacao(doc = document) {
    doc.addEventListener('change', (evento) => {
        if (evento.target.matches?.('[data-arquivos-curriculo]')) {
            mostrarArquivosSelecionados(evento.target, doc);
        }
    });

    doc.addEventListener('click', (evento) => {
        const botao = evento.target.closest?.('[data-ignorar-sugestao]');
        if (!botao) {
            return;
        }
        evento.preventDefault();
        botao.closest('[data-sugestao]')?.remove();
    });

    doc.addEventListener('ajax:sucesso', (evento) => {
        const formulario = evento.target;
        if (!formulario.matches?.('[data-sugestao-form]')) {
            return;
        }
        formulario.closest('[data-sugestao]')?.remove();
        const [nova] = evento.detail?.novos ?? [];
        if (nova !== undefined) {
            focarCompetencia(doc, nova);
        }
    });
}

if (typeof document !== 'undefined') {
    registrarImportacao();
}
