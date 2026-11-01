/**
 * Revisão da importação de currículo (US2): "Ignorar" retira a sugestão da lista sem chamar o
 * servidor; incluir uma sugestão no catálogo (formulário data-sugestao-form, mesclado por
 * formularios.js) retira a sugestão e leva o foco à nova opção, já marcada.
 */
function focarCompetencia(doc, chave) {
    const regiao = doc.getElementById('catalogo-competencias');
    const item = Array.from(regiao?.querySelectorAll('[data-chave]') ?? []).find((elemento) => elemento.dataset.chave === chave);
    item?.querySelector('input')?.focus();
}

export function registrarImportacao(doc = document) {
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
