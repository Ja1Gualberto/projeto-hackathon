import { anunciar, aplicarFragmentos, ErroAjax, limparErros, mesmaOrigem, mostrarErros, solicitar } from './ajax.js';
import { suportaAjax } from './formularios.js';

export function configurarBusca(formulario, janela = window) {
    const doc = formulario.ownerDocument;
    const regiao = doc.getElementById('resultados-candidatos');
    let sequencia = 0;
    let controlador;
    let temporizador;
    let urlConfirmada = janela.location.href;

    function restaurarCampos(url) {
        for (const campo of formulario.elements) {
            if (!campo.name || ['submit', 'button'].includes(campo.type)) {
                continue;
            }
            const valores = url.searchParams.getAll(campo.name);
            // Links de paginação do PHP serializam arrays como competencias[0].
            if (campo.name.endsWith('[]')) {
                const prefixo = campo.name.slice(0, -2);
                for (const [nome, valor] of url.searchParams) {
                    if (nome.startsWith(`${prefixo}[`) && nome !== campo.name) {
                        valores.push(valor);
                    }
                }
            }
            if (['checkbox', 'radio'].includes(campo.type)) {
                campo.checked = valores.includes(campo.value);
            } else if (campo.value !== (valores[0] ?? '')) {
                campo.value = valores[0] ?? '';
            }
        }
    }

    function invalidar() {
        clearTimeout(temporizador);
        controlador?.abort();
        sequencia++;
        regiao.removeAttribute('aria-busy');
        return sequencia;
    }

    function urlDosCampos() {
        const url = mesmaOrigem(formulario.action, doc.baseURI);
        url.search = new URLSearchParams(new FormData(formulario)).toString();
        url.searchParams.delete('page');
        return url;
    }

    async function consultar(url, modo, intencao, sincronizar = false, focarResultado = false) {
        controlador = new AbortController();
        regiao.setAttribute('aria-busy', 'true');
        limparErros(formulario);
        anunciar('Buscando talentos…', doc);
        try {
            const dados = await solicitar(url, { signal: controlador.signal, base: doc.baseURI });
            if (intencao !== sequencia) {
                return;
            }
            if (dados.redirect !== null || Object.keys(dados.fragments).length !== 1 || typeof dados.fragments['resultados-candidatos'] !== 'string') {
                throw new ErroAjax('O servidor enviou uma resposta de busca inesperada.');
            }
            const restaurarFoco = focarResultado && regiao.contains(doc.activeElement);
            aplicarFragmentos(dados, doc);
            if (sincronizar) {
                restaurarCampos(url);
            }
            if (modo !== 'popstate') {
                janela.history[modo === 'pagina' ? 'pushState' : 'replaceState'](null, '', url.href);
            }
            urlConfirmada = url.href;
            const titulo = regiao.querySelector('#resultados-titulo');
            anunciar(titulo?.textContent ?? 'Resultados atualizados.', doc);
            if (restaurarFoco) {
                titulo?.focus();
            }
        } catch (erro) {
            if (intencao !== sequencia || erro.name === 'AbortError') {
                return;
            }
            if (modo === 'popstate') {
                janela.history.replaceState(null, '', urlConfirmada);
                restaurarCampos(new URL(urlConfirmada));
            }
            mostrarErros(formulario, erro, { focar: erro.status === 422 });
        } finally {
            if (intencao === sequencia) {
                regiao.removeAttribute('aria-busy');
            }
        }
    }

    function alterar(evento) {
        const campo = evento.target;
        if (!campo.name || campo.closest('[data-sem-autoenvio]')) {
            return;
        }
        const texto = campo.matches('input[type="text"], input[type="search"], textarea');
        if ((evento.type === 'input') !== texto) {
            return;
        }
        const intencao = invalidar();
        if (texto) {
            temporizador = setTimeout(() => consultar(urlDosCampos(), 'filtros', intencao), 400);
        } else {
            consultar(urlDosCampos(), 'filtros', intencao);
        }
    }

    formulario.addEventListener('input', alterar);
    formulario.addEventListener('change', alterar);
    formulario.addEventListener('submit', (evento) => {
        evento.preventDefault();
        consultar(urlDosCampos(), 'filtros', invalidar());
    });
    doc.addEventListener('click', (evento) => {
        if (evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) {
            return;
        }
        const link = evento.target.closest('[data-limpar-busca], [data-paginacao-busca] a');
        if (!link || (!link.closest('#resultados-candidatos') && !formulario.contains(link))) {
            return;
        }
        const url = mesmaOrigem(link.href, doc.baseURI);
        if (url.pathname !== new URL(formulario.action).pathname) {
            return;
        }
        evento.preventDefault();
        const limpar = link.hasAttribute('data-limpar-busca');
        consultar(url, limpar ? 'filtros' : 'pagina', invalidar(), true, link.closest('#resultados-candidatos') !== null);
    });
    janela.addEventListener('popstate', () => {
        const intencao = invalidar();
        const url = mesmaOrigem(janela.location.href, doc.baseURI);
        restaurarCampos(url);
        consultar(url, 'popstate', intencao);
    });
}

if (typeof document !== 'undefined' && suportaAjax() && globalThis.history?.pushState) {
    document.querySelectorAll('form[data-busca-candidatos]').forEach((formulario) => configurarBusca(formulario));
}
