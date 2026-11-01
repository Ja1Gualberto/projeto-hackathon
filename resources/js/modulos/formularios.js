import { anunciar, aplicarFragmentos, limparErros, mesmaOrigem, mostrarErros, solicitar } from './ajax.js';

const pendentes = new WeakSet();
const temporizadores = new WeakMap();
const SELETOR_TEXTO = 'input[type="text"], input[type="search"], textarea';

export function suportaAjax() {
    return typeof fetch === 'function' && typeof FormData === 'function' && typeof AbortController === 'function';
}

export async function enviarFormulario(formulario, botao = null, confirmar = (texto) => globalThis.confirm?.(texto) ?? false) {
    if (pendentes.has(formulario) || (formulario.dataset.confirmar && !confirmar(formulario.dataset.confirmar))) {
        return;
    }
    pendentes.add(formulario);
    let controles = [];
    let erroEnvio;
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
        aplicarFragmentos(resposta, formulario.ownerDocument);
        anunciar(resposta.message ?? 'Operação concluída.', formulario.ownerDocument);
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
        }
    });

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
