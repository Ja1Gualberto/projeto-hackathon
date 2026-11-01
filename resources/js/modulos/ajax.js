import { mostrarAlerta } from './alertas.js';

const REGIOES = new Set([
    'resultados-candidatos', 'catalogo-competencias', 'quadro-vaga', 'perfil-na-vaga',
    'historico-anotacoes', 'competencias-candidato', 'situacao-candidato', 'secao-lgpd', 'secao-energia',
]);

const MENSAGENS = {
    401: 'Sua sessão expirou. Abra o login em outra aba e confira os dados antes de continuar.',
    403: 'Você não tem permissão para esta ação ou a origem foi recusada.',
    404: 'Este recurso não está mais disponível. Volte à listagem ou envie novamente.',
    413: 'O arquivo excede o limite de 10 MB.',
    419: 'Sua sessão ou token expirou. Atualize a página conscientemente antes de continuar.',
    422: 'Revise os campos indicados para continuar.',
    503: 'Análise indisponível no momento.',
};

export class ErroAjax extends Error {
    constructor(message, status = 0, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

export function mesmaOrigem(destino, base = globalThis.location?.href) {
    const url = new URL(destino, base);
    if (!['http:', 'https:'].includes(url.protocol) || url.origin !== new URL(base).origin || url.username || url.password) {
        throw new ErroAjax('Destino de outra origem recusado.');
    }
    return url;
}

function objeto(valor) {
    return valor !== null && typeof valor === 'object' && !Array.isArray(valor);
}

export function validarEnvelope(dados, base = globalThis.location?.href) {
    if (!objeto(dados) || Object.keys(dados).length !== 3
        || !(dados.message === null || typeof dados.message === 'string')
        || !(dados.redirect === null || typeof dados.redirect === 'string') || !objeto(dados.fragments)) {
        throw new ErroAjax('O servidor enviou uma resposta inesperada.');
    }
    for (const [regiao, html] of Object.entries(dados.fragments)) {
        if (!REGIOES.has(regiao) || typeof html !== 'string') {
            throw new ErroAjax('O servidor enviou uma região inesperada.');
        }
    }
    if (dados.redirect !== null) {
        mesmaOrigem(dados.redirect, base);
    }
    return dados;
}

export async function solicitar(destino, { method = 'GET', body, signal, base = globalThis.location?.href } = {}) {
    const url = mesmaOrigem(destino, base);
    let resposta;
    try {
        resposta = await fetch(url, {
            method, ...(method.toUpperCase() === 'GET' ? {} : { body }), signal,
            credentials: 'same-origin', mode: 'same-origin', redirect: 'error', cache: 'no-store',
            headers: { Accept: 'application/json' },
        });
    } catch (erro) {
        if (signal?.aborted || erro.name === 'AbortError') {
            throw erro;
        }
        throw new ErroAjax('Não foi possível confirmar a operação. Confira o estado pela página de consulta antes de tentar novamente.');
    }
    let dados;
    if (resposta.headers.get('Content-Type')?.includes('application/json')) {
        try {
            dados = await resposta.json();
        } catch {
            if (resposta.ok) {
                throw new ErroAjax('O servidor enviou uma resposta inesperada.');
            }
        }
    }
    if (!resposta.ok) {
        const erros = resposta.status === 422 && objeto(dados?.errors)
            && Object.values(dados.errors).every((mensagens) => Array.isArray(mensagens) && mensagens.every((texto) => typeof texto === 'string'))
            ? dados.errors : {};
        throw new ErroAjax(MENSAGENS[resposta.status] ?? 'O servidor não confirmou a operação. Confira o estado antes de tentar novamente.', resposta.status, erros);
    }
    if (resposta.status !== 200 || resposta.redirected) {
        throw new ErroAjax('O servidor enviou uma resposta inesperada.');
    }
    return validarEnvelope(dados, base);
}

export function aplicarFragmentos(dados, doc = document, base = doc.baseURI) {
    validarEnvelope(dados, base);
    const atualizacoes = Object.entries(dados.fragments).map(([id, html]) => {
        const elemento = doc.getElementById(id);
        if (!elemento) {
            throw new ErroAjax('A região de atualização não está disponível nesta página.');
        }
        return [elemento, html];
    });
    for (const [elemento, html] of atualizacoes) {
        elemento.innerHTML = html;
    }
}

/**
 * Inclui na região apenas os itens novos do fragmento (identificados por data-chave), no grupo
 * correspondente (data-grupo). Itens existentes não são tocados, preservando o que o usuário
 * marcou. Devolve as chaves incluídas.
 */
export function mesclarFragmento(dados, regiaoId, doc = document, base = doc.baseURI) {
    validarEnvelope(dados, base);
    const regiao = doc.getElementById(regiaoId);
    const html = dados.fragments[regiaoId];
    if (!regiao || typeof html !== 'string' || Object.keys(dados.fragments).length !== 1) {
        throw new ErroAjax('O servidor enviou uma resposta inesperada.');
    }
    const modelo = doc.createElement('template');
    modelo.innerHTML = html;
    const existentes = new Set(Array.from(regiao.querySelectorAll('[data-chave]'), (item) => item.dataset.chave));
    const grupos = new Map(Array.from(regiao.querySelectorAll('[data-grupo]'), (grupo) => [grupo.dataset.grupo, grupo]));
    const novos = [];
    for (const item of modelo.content.querySelectorAll('[data-chave]')) {
        const grupo = grupos.get(item.closest('[data-grupo]')?.dataset.grupo);
        const destino = grupo?.querySelector('[data-grupo-itens]');
        if (existentes.has(item.dataset.chave) || !destino) {
            continue;
        }
        destino.append(doc.importNode(item, true));
        grupo.querySelector('[data-grupo-vazio]')?.remove();
        existentes.add(item.dataset.chave);
        novos.push(item.dataset.chave);
    }
    return novos;
}

export function anunciar(mensagem, doc = document, tipo = null) {
    const alerta = tipo ? mostrarAlerta(mensagem, doc, tipo) : null;
    const regiao = doc.querySelector('[data-ajax-anuncios]');
    if (regiao) {
        regiao.textContent = alerta ? '' : mensagem ?? '';
    }
    return alerta;
}

export function limparErros(formulario) {
    formulario.querySelectorAll('[data-erro-dinamico]').forEach((erro) => erro.remove());
    formulario.querySelectorAll('[data-erro-campo]').forEach((erro) => {
        erro.textContent = '';
        erro.hidden = true;
    });
    formulario.querySelectorAll('[aria-invalid]').forEach((campo) => {
        campo.removeAttribute('aria-invalid');
        const descricao = (campo.getAttribute('aria-describedby') ?? '').split(' ').filter((id) => id && !id.startsWith('ajax-erro-')).join(' ');
        if (descricao) {
            campo.setAttribute('aria-describedby', descricao);
        } else {
            campo.removeAttribute('aria-describedby');
        }
    });
    const resumo = formulario.querySelector('[data-ajax-erros]');
    if (resumo) {
        resumo.textContent = '';
        resumo.hidden = true;
    }
}

let numeroErro = 0;

export function mostrarErros(formulario, erro, { focar = true } = {}) {
    const doc = formulario.ownerDocument;
    let resumo = formulario.querySelector('[data-ajax-erros]');
    if (!resumo) {
        resumo = doc.createElement('div');
        resumo.dataset.ajaxErros = '';
        resumo.className = 'rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800';
        resumo.setAttribute('role', 'alert');
        resumo.tabIndex = -1;
        formulario.prepend(resumo);
    }
    resumo.hidden = false;
    resumo.className = 'sr-only';
    resumo.removeAttribute?.('role');
    resumo.textContent = [erro.message, ...Object.values(erro.errors ?? {}).flat()].join(' ');
    let primeiro;
    const avisosFixos = Array.from(formulario.querySelectorAll('[data-erro-campo]'));
    // Rádios e checkboxes de um mesmo grupo compartilham um único aviso.
    const avisosDeGrupo = new Map();
    for (const [nome, mensagens] of Object.entries(erro.errors ?? {})) {
        const nomeBase = nome.replace(/\.\d+$/, '');
        const campos = Array.from(formulario.elements).filter((campo) => {
            const chave = (campo.name ?? '').replace(/\[([^\]]*)\]/g, '.$1').replace(/\.$/, '');
            return chave === nome || chave === nomeBase;
        });
        for (const campo of campos) {
            const agrupado = ['radio', 'checkbox'].includes(campo.type);
            let aviso = agrupado ? avisosDeGrupo.get(nomeBase) : undefined;
            aviso ??= avisosFixos.find((elemento) => [nome, nomeBase].includes(elemento.dataset.erroCampo)
                && (agrupado || elemento.id === `${campo.id}-erro`));
            if (!aviso) {
                aviso = doc.createElement('p');
                aviso.id = `ajax-erro-${++numeroErro}`;
                aviso.dataset.erroDinamico = '';
                aviso.className = 'text-sm font-medium text-red-700';
                campo.insertAdjacentElement('afterend', aviso);
            }
            if (agrupado) {
                avisosDeGrupo.set(nomeBase, aviso);
            }
            aviso.hidden = false;
            aviso.textContent = mensagens.join(' ');
            campo.setAttribute('aria-invalid', 'true');
            const descricoes = new Set((campo.getAttribute('aria-describedby') ?? '').split(' ').filter(Boolean));
            descricoes.add(aviso.id);
            campo.setAttribute('aria-describedby', [...descricoes].join(' '));
            primeiro ??= campo;
        }
    }
    const alerta = anunciar(resumo.textContent, doc, 'erro');
    if (focar) {
        (primeiro ?? alerta?.querySelector('[data-alerta-fechar]') ?? resumo).focus?.();
    }
    if (erro.status === 401) {
        const login = doc.querySelector('[data-ajax-login]');
        if (login) {
            login.hidden = false;
        }
    }
    if (!erro.status || (erro.status >= 500 && erro.status !== 503)) {
        const consulta = doc.querySelector('[data-ajax-conferir]');
        if (consulta) {
            consulta.hidden = false;
        }
    }
}
