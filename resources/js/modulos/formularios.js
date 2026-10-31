/**
 * Comportamentos de formulário compartilhados (T040):
 * - `form[data-autosubmit]`: envia ao alterar qualquer campo (texto com espera de 400 ms).
 *   Campos dentro de `[data-sem-autoenvio]` (ex.: filtro de chips) não disparam o envio.
 * - `[data-abrir-modal="id"]` abre o `<dialog id="id">`; `[data-fechar-modal]` fecha o diálogo atual.
 * - `form[data-confirmar="mensagem"]` pede confirmação antes do envio.
 */
const ESPERA_TEXTO_MS = 400;
const SELETOR_TEXTO = 'input[type="text"], input[type="search"]';

function ignorarAutoenvio(elemento) {
    return elemento.closest('[data-sem-autoenvio]') !== null;
}

function configurarAutoenvio(formulario) {
    let temporizador;

    formulario.addEventListener('input', (evento) => {
        if (!evento.target.matches(SELETOR_TEXTO) || ignorarAutoenvio(evento.target)) {
            return;
        }

        clearTimeout(temporizador);
        temporizador = setTimeout(() => formulario.requestSubmit(), ESPERA_TEXTO_MS);
    });

    formulario.addEventListener('change', (evento) => {
        if (evento.target.matches(SELETOR_TEXTO) || ignorarAutoenvio(evento.target)) {
            return;
        }

        formulario.requestSubmit();
    });
}

document.querySelectorAll('form[data-autosubmit]').forEach(configurarAutoenvio);

document.addEventListener('click', (evento) => {
    const abrir = evento.target.closest('[data-abrir-modal]');

    if (abrir) {
        const dialogo = document.getElementById(abrir.dataset.abrirModal);

        if (dialogo instanceof HTMLDialogElement) {
            evento.preventDefault();
            dialogo.showModal();
        }

        return;
    }

    if (evento.target.closest('[data-fechar-modal]')) {
        evento.target.closest('dialog')?.close();

        return;
    }

    // Clique no fundo escurecido (fora do conteúdo) fecha o diálogo.
    if (evento.target instanceof HTMLDialogElement) {
        evento.target.close();
    }
});

document.addEventListener('submit', (evento) => {
    const formulario = evento.target;

    if (formulario instanceof HTMLFormElement && formulario.dataset.confirmar && !window.confirm(formulario.dataset.confirmar)) {
        evento.preventDefault();
    }
});
