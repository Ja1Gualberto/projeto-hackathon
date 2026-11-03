import { anunciar, aplicarFragmentos, ErroAjax, solicitar } from './ajax.js';
import { fecharAlertas } from './alertas.js';
import { enviarFormulario, suportaAjax } from './formularios.js';

/**
 * Quadro da vaga e perfil na vaga (US3/US4).
 * - "Atualizar quadro" é um link GET real: com AJAX, refaz a leitura e substitui só `quadro-vaga`,
 *   por ação explícita (sem polling).
 * - Os botões de etapa (form[data-mover-etapa]) usam o transporte comum de formularios.js. Enquanto
 *   uma ação está pendente, o quadro não aceita outra; nada é movido nem revelado antes da resposta
 *   do servidor, que devolve os dados finais para o JavaScript montar o quadro.
 * - Arrastar e soltar (opcional, T111) envia o mesmo formulário do card com a etapa da coluna de destino.
 * Encerrar compartilha o bloqueio do quadro com as movimentações e a atualização.
 */
const MENSAGENS_PROPRIAS = new Set([401, 403, 404, 419]);
const REGIOES_DE_MOVIMENTACAO = '#quadro-vaga, #perfil-na-vaga';

export function registrarQuadro(doc = document) {
    let pendente = false;
    let arrastado = null;
    const animacoesDetalhes = new WeakMap();

    function animarDetalhes(card, detalhes, aberto) {
        const janela = doc.defaultView;
        const anterior = animacoesDetalhes.get(detalhes);
        if (!detalhes.animate || janela?.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            anterior?.cancel();
            animacoesDetalhes.delete(detalhes);
            detalhes.hidden = !aberto;
            return;
        }

        function medir() {
            const estilo = janela.getComputedStyle(detalhes);
            return {
                height: estilo.height,
                paddingTop: estilo.paddingTop,
                borderTopWidth: estilo.borderTopWidth,
                marginBottom: estilo.marginBottom,
                opacity: estilo.opacity,
                overflow: 'hidden',
                minHeight: '0px',
            };
        }

        const recolhido = {
            height: '0px', paddingTop: '0px', borderTopWidth: '0px',
            marginBottom: `-${janela.getComputedStyle(card).rowGap}`,
            opacity: '0', overflow: 'hidden', minHeight: '0px',
        };
        const inicio = detalhes.hidden ? recolhido : medir();
        anterior?.cancel();
        detalhes.hidden = false;
        const destino = aberto ? medir() : recolhido;
        const animacao = detalhes.animate([inicio, destino], {
            duration: 320,
            easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
            fill: 'both',
        });
        animacoesDetalhes.set(detalhes, animacao);
        animacao.onfinish = () => {
            if (animacoesDetalhes.get(detalhes) !== animacao) {
                return;
            }
            detalhes.hidden = !aberto;
            animacoesDetalhes.delete(detalhes);
            animacao.cancel();
        };
    }

    async function mover(formulario, botao) {
        const regiao = formulario.closest(REGIOES_DE_MOVIMENTACAO);
        if (!regiao || pendente) {
            return;
        }
        const codigo = formulario.closest('[data-codigo]')?.dataset.codigo;
        pendente = true;
        regiao.setAttribute('aria-busy', 'true');
        // A mensagem anterior pode citar um nome revelado: cada movimentação a substitui, para o nome
        // não sobrar ao lado do código depois de uma volta à triagem nem empilhar alertas sobre o quadro.
        fecharAlertas(doc);
        try {
            await enviarFormulario(formulario, botao);
        } finally {
            regiao.removeAttribute('aria-busy');
            pendente = false;
        }
        // Com sucesso, o formulário saiu do documento junto com a região antiga: o foco vai ao card movido.
        if (formulario.isConnected === false && codigo) {
            const card = Array.from(regiao.querySelectorAll('[data-codigo]')).find((item) => item.dataset.codigo === codigo);
            card?.querySelector('[data-link-perfil]')?.focus();
        }
    }

    function limparAlvos() {
        doc.querySelectorAll('[data-alvo-soltar]').forEach((coluna) => coluna.removeAttribute('data-alvo-soltar'));
    }

    function colunaDeDestino(evento) {
        const coluna = evento.target.closest?.('[data-etapa]');
        const regiao = doc.getElementById('quadro-vaga');
        if (!arrastado || pendente || !coluna || !regiao?.contains(coluna) || coluna.dataset.etapa === arrastado.origem) {
            return null;
        }
        return coluna;
    }

    doc.addEventListener('click', async (evento) => {
        if (evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) {
            return;
        }
        const regiao = doc.getElementById('quadro-vaga');
        const expandir = evento.target.closest?.('[data-expandir-card]');
        if (expandir && regiao?.contains(expandir)) {
            const card = expandir.closest('[data-codigo]');
            const detalhes = card.querySelector('[data-card-detalhes]');
            const identificacao = card.querySelector('[data-link-perfil]').textContent;
            const aberto = expandir.getAttribute('aria-expanded') !== 'true';
            evento.preventDefault();
            animarDetalhes(card, detalhes, aberto);
            expandir.setAttribute('aria-expanded', String(aberto));
            expandir.setAttribute('aria-label', `${aberto ? 'Ocultar' : 'Mostrar'} competências de ${identificacao}`);
            return;
        }
        const link = evento.target.closest?.('[data-atualizar-quadro]');
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
            const regioes = [...Object.keys(dados.fragments), ...Object.keys(dados.data ?? {})];
            if (dados.redirect !== null || regioes.length !== 1 || regioes[0] !== 'quadro-vaga') {
                throw new ErroAjax('O servidor enviou uma resposta inesperada.');
            }
            aplicarFragmentos(dados, doc);
            anunciar(dados.message ?? 'Quadro atualizado.', doc, 'sucesso');
            regiao.querySelector('[data-atualizar-quadro]')?.focus();
        } catch (erro) {
            // Leitura não altera nada no servidor: o quadro anterior fica na tela e pode ser recarregado.
            anunciar(MENSAGENS_PROPRIAS.has(erro.status) ? erro.message : 'Não foi possível atualizar o quadro. O quadro anterior foi mantido; tente novamente.', doc, 'erro');
            const login = erro.status === 401 ? doc.querySelector('[data-ajax-login]') : null;
            if (login) {
                login.hidden = false;
            }
        } finally {
            regiao.removeAttribute('aria-busy');
            pendente = false;
        }
    });

    doc.addEventListener('submit', (evento) => {
        const formulario = evento.target;
        if (!formulario.matches?.('form[data-mover-etapa]') && !formulario.matches?.('form[data-encerrar-vaga]')) {
            return undefined;
        }
        evento.preventDefault();
        return mover(formulario, evento.submitter);
    });

    doc.addEventListener('dragstart', (evento) => {
        const card = evento.target.closest?.('[draggable="true"][data-codigo]');
        const regiao = doc.getElementById('quadro-vaga');
        const formulario = card?.querySelector('form[data-mover-etapa]');
        const origem = card?.closest('[data-etapa]')?.dataset.etapa;
        if (!card || !formulario || !origem || pendente || !regiao?.contains(card)) {
            return;
        }
        arrastado = { formulario, origem };
        evento.dataTransfer?.setData('text/plain', card.dataset.codigo);
        if (evento.dataTransfer) {
            evento.dataTransfer.effectAllowed = 'move';
        }
    });

    doc.addEventListener('dragover', (evento) => {
        const coluna = colunaDeDestino(evento);
        if (!coluna) {
            return;
        }
        evento.preventDefault();
        if (evento.dataTransfer) {
            evento.dataTransfer.dropEffect = 'move';
        }
        coluna.setAttribute('data-alvo-soltar', '');
    });

    doc.addEventListener('dragleave', (evento) => {
        const coluna = evento.target.closest?.('[data-etapa]');
        if (coluna && !coluna.contains?.(evento.relatedTarget)) {
            coluna.removeAttribute('data-alvo-soltar');
        }
    });

    doc.addEventListener('drop', (evento) => {
        const coluna = colunaDeDestino(evento);
        if (!coluna) {
            return undefined;
        }
        evento.preventDefault();
        const { formulario } = arrastado;
        arrastado = null;
        limparAlvos();
        return mover(formulario, { name: 'etapa', value: coluna.dataset.etapa });
    });

    doc.addEventListener('dragend', () => {
        arrastado = null;
        limparAlvos();
    });
}

if (typeof document !== 'undefined' && suportaAjax()) {
    registrarQuadro();
}
