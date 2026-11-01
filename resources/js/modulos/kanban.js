import { anunciar, aplicarFragmentos, ErroAjax, solicitar } from './ajax.js';
import { suportaAjax } from './formularios.js';

/**
 * Quadro da vaga (US3). "Atualizar quadro" é um link GET real: com AJAX, refaz a leitura do
 * ranking e substitui só a região `quadro-vaga`, por ação explícita (sem polling). Encerrar a
 * vaga usa o formulário data-ajax comum (formularios.js). Arrastar e soltar entre colunas: T111 (US4).
 */
const MENSAGENS_PROPRIAS = new Set([401, 403, 404, 419]);

export function registrarQuadro(doc = document) {
    let pendente = false;

    doc.addEventListener('click', async (evento) => {
        if (evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) {
            return;
        }
        const link = evento.target.closest?.('[data-atualizar-quadro]');
        const regiao = doc.getElementById('quadro-vaga');
        if (!link || !regiao?.contains(link)) {
            return;
        }
        evento.preventDefault();
        if (pendente) {
            return;
        }
        pendente = true;
        regiao.setAttribute('aria-busy', 'true');
        anunciar('Atualizando quadro…', doc);
        try {
            const dados = await solicitar(link.href, { base: doc.baseURI });
            if (dados.redirect !== null || Object.keys(dados.fragments).length !== 1 || typeof dados.fragments['quadro-vaga'] !== 'string') {
                throw new ErroAjax('O servidor enviou uma resposta inesperada.');
            }
            aplicarFragmentos(dados, doc);
            anunciar(dados.message ?? 'Quadro atualizado.', doc);
            regiao.querySelector('[data-atualizar-quadro]')?.focus();
        } catch (erro) {
            // Leitura não altera nada no servidor: o quadro anterior fica na tela e pode ser recarregado.
            anunciar(MENSAGENS_PROPRIAS.has(erro.status) ? erro.message : 'Não foi possível atualizar o quadro. O quadro anterior foi mantido; tente novamente.', doc);
            const login = erro.status === 401 ? doc.querySelector('[data-ajax-login]') : null;
            if (login) {
                login.hidden = false;
            }
        } finally {
            regiao.removeAttribute('aria-busy');
            pendente = false;
        }
    });
}

if (typeof document !== 'undefined' && suportaAjax()) {
    registrarQuadro();
}
