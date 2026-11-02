import { anunciar, aplicarFragmentos, limparErros, mesclarFragmento, mesmaOrigem, mostrarErros, solicitar } from './ajax.js';

const pendentes = new WeakSet();
const temporizadores = new WeakMap();
const botoesEmEnvio = new WeakMap();
const SELETOR_TEXTO = 'input[type="text"], input[type="search"], textarea';

/**
 * Quando o próprio formulário é substituído junto com a região (ex.: Encerrar vaga no quadro),
 * o foco iria para o documento; leva-o ao ponto indicado pelo novo HTML com data-foco-apos-ajax.
 */
function focarRegiaoAtualizada(regioes, doc) {
    for (const id of regioes) {
        const alvo = doc.getElementById(id)?.querySelector('[data-foco-apos-ajax]');
        if (alvo) {
            alvo.focus();
            return;
        }
    }
}

export function suportaAjax() {
    return typeof fetch === 'function' && typeof FormData === 'function' && typeof AbortController === 'function';
}

/**
 * Formulário tradicional (login, ou navegador sem fetch): o botão data-carregando mostra
 * "Processando…" até a próxima página. Só bloqueia depois do evento, senão o navegador deixaria
 * de enviar o name/value do botão.
 */
function indicarEnvioTradicional(formulario) {
    const botao = formulario.querySelector('[data-carregando]');
    if (!botao) {
        return;
    }
    const conteudo = Array.from(botao.childNodes);
    setTimeout(() => {
        botao.disabled = true;
        botao.textContent = 'Processando…';
    });
    botoesEmEnvio.set(botao, conteudo);
}

/**
 * Ao voltar pelo histórico, o navegador pode restaurar a página como estava: libera os botões.
 */
function restaurarBotoesEmEnvio(doc) {
    doc.querySelectorAll('[data-carregando]').forEach((botao) => {
        const conteudo = botoesEmEnvio.get(botao);
        if (conteudo) {
            botao.disabled = false;
            botao.replaceChildren(...conteudo);
            botoesEmEnvio.delete(botao);
        }
    });
}

export async function enviarFormulario(formulario, botao = null, confirmar = (texto) => globalThis.confirm?.(texto) ?? false) {
    if (pendentes.has(formulario) || (formulario.dataset.confirmar && !confirmar(formulario.dataset.confirmar))) {
        return;
    }
    pendentes.add(formulario);
    let controles = [];
    let erroEnvio;
    let limparDepois = false;
    try {
        // Serializar antes de bloquear: controles disabled não participam do FormData.
        const dados = new FormData(formulario);
        if (botao?.name) {
            dados.append(botao.name, botao.value);
        }
        const method = formulario.method.toUpperCase();
        const url = mesmaOrigem(formulario.action, formulario.ownerDocument.baseURI);
        if (method === 'GET') {
            url.search = new URLSearchParams(dados).toString();
        }
        limparErros(formulario);
        controles = Array.from(formulario.elements).map((campo) => ({
            campo, disabled: campo.disabled,
            texto: campo.matches('button[type="submit"], button:not([type])') ? campo.textContent : null,
            conteudo: campo.childNodes ? Array.from(campo.childNodes) : null,
        }));
        controles.forEach(({ campo, texto }) => {
            campo.disabled = true;
            if (texto !== null) {
                campo.textContent = 'Processando…';
            }
        });
        formulario.setAttribute('aria-busy', 'true');
        anunciar('Processando…', formulario.ownerDocument);
        const resposta = await solicitar(url, { method, body: dados, base: formulario.ownerDocument.baseURI });
        // data-ajax-mesclar="regiao": inclui só itens novos, sem apagar o que já foi editado na página.
        const novos = formulario.dataset.ajaxMesclar
            ? mesclarFragmento(resposta, formulario.dataset.ajaxMesclar, formulario.ownerDocument)
            : (aplicarFragmentos(resposta, formulario.ownerDocument), []);
        if (formulario.isConnected === false) {
            focarRegiaoAtualizada(Object.keys(resposta.fragments), formulario.ownerDocument);
        }
        anunciar(resposta.message ?? 'Operação concluída.', formulario.ownerDocument, 'sucesso');
        formulario.dispatchEvent?.(new CustomEvent('ajax:sucesso', { bubbles: true, detail: { resposta, novos } }));
        limparDepois = formulario.hasAttribute?.('data-limpar-apos-sucesso') === true;
        if (resposta.redirect) {
            globalThis.location.assign(mesmaOrigem(resposta.redirect, formulario.ownerDocument.baseURI).href);
        }
    } catch (erro) {
        erroEnvio = erro;
    } finally {
        controles.forEach(({ campo, disabled, texto, conteudo }) => {
            campo.disabled = disabled;
            if (texto !== null && conteudo !== null) {
                campo.replaceChildren(...conteudo);
            } else if (texto !== null) {
                campo.textContent = texto;
            }
        });
        formulario.removeAttribute('aria-busy');
        pendentes.delete(formulario);
    }
    if (erroEnvio) {
        mostrarErros(formulario, erroEnvio);
    } else if (limparDepois) {
        // data-limpar-apos-sucesso: só depois de confirmado pelo servidor; em erro, o rascunho fica.
        formulario.reset();
        // reset() não dispara "input": os filtros de chips voltam a mostrar todas as opções.
        formulario.querySelectorAll('[data-filtrar-chips]').forEach((filtro) => filtro.dispatchEvent(new Event('input')));
        formulario.querySelector('[data-foco-apos-limpar]')?.focus();
    }
}

export function registrarFormularios(doc = document) {
    doc.addEventListener('submit', (evento) => {
        const formulario = evento.target;
        if (formulario.matches('form[data-ajax]') && suportaAjax()) {
            evento.preventDefault();
            clearTimeout(temporizadores.get(formulario));
            return enviarFormulario(formulario, evento.submitter);
        }
        if (formulario.dataset.confirmar && !(globalThis.confirm?.(formulario.dataset.confirmar) ?? false)) {
            evento.preventDefault();
            return undefined;
        }
        return indicarEnvioTradicional(formulario);
    });
    doc.defaultView?.addEventListener('pageshow', () => restaurarBotoesEmEnvio(doc));

    const autoenviar = (evento) => {
        const campo = evento.target;
        const formulario = campo.closest('form[data-autosubmit]');
        if (!formulario || campo.closest('[data-sem-autoenvio]') || formulario.hasAttribute('data-busca-candidatos')) {
            return;
        }
        const texto = campo.matches(SELETOR_TEXTO);
        if ((evento.type === 'input') !== texto) {
            return;
        }
        clearTimeout(temporizadores.get(formulario));
        if (texto) {
            temporizadores.set(formulario, setTimeout(() => formulario.requestSubmit(), 400));
        } else {
            formulario.requestSubmit();
        }
    };
    doc.addEventListener('input', autoenviar);
    doc.addEventListener('change', autoenviar);
    doc.addEventListener('click', (evento) => {
        const abrir = evento.target.closest('[data-abrir-modal]');
        if (abrir) {
            const dialogo = doc.getElementById(abrir.dataset.abrirModal);
            if (typeof dialogo?.showModal === 'function') {
                evento.preventDefault();
                dialogo.showModal();
            }
            return;
        }
        if (evento.target.closest('[data-fechar-modal]')) {
            evento.preventDefault();
            evento.target.closest('dialog')?.close();
        } else if (evento.target.matches('dialog')) {
            evento.target.close();
        }
    });
}

if (typeof document !== 'undefined') {
    registrarFormularios();
}
