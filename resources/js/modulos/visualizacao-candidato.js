import { ErroAjax, solicitar } from './ajax.js';

export function registrarVisualizacaoCandidato(doc = document) {
    const dialogo = doc.getElementById('curriculo-analise-quadro');
    if (!dialogo?.showModal) {
        return;
    }

    const conteudo = doc.getElementById('visualizacao-candidato');
    const nome = dialogo.querySelector('[data-visualizacao-nome]');
    const carregando = dialogo.querySelector('[data-visualizacao-carregando]');
    const falha = dialogo.querySelector('[data-visualizacao-falha]');
    const mensagem = dialogo.querySelector('[data-visualizacao-erro]');
    let controle = null;
    let destino = null;
    const geracoesPendentes = new WeakSet();

    async function gerarAnalise(formulario) {
        if (!controle || geracoesPendentes.has(formulario)) {
            return;
        }
        const atual = controle;
        const regiao = conteudo.querySelector('#visualizacao-energia');
        const botao = formulario.querySelector('[data-gerar-analise-botao]');
        const status = regiao.querySelector('[data-analise-status]');
        const erroLocal = regiao.querySelector('[data-analise-erro]');
        const textoOriginal = botao.textContent;
        geracoesPendentes.add(formulario);

        try {
            const dados = new FormData(formulario);
            botao.disabled = true;
            botao.textContent = 'Gerando análise…';
            regiao.setAttribute('aria-busy', 'true');
            erroLocal.hidden = true;
            status.textContent = 'Gerando análise de IA. Isso pode levar alguns instantes.';
            status.hidden = false;

            const resposta = await solicitar(formulario.action, { method: 'POST', body: dados, signal: atual.signal, base: doc.baseURI });
            if (atual.signal.aborted || controle !== atual || !dialogo.open) {
                return;
            }
            if (resposta.redirect !== null || Object.keys(resposta.data ?? {}).length !== 0
                || Object.keys(resposta.fragments).length !== 1 || typeof resposta.fragments['visualizacao-energia'] !== 'string') {
                throw new ErroAjax('O servidor enviou uma resposta inesperada.');
            }
            regiao.innerHTML = resposta.fragments['visualizacao-energia'];
            const aviso = regiao.querySelector('[data-analise-status]');
            aviso.textContent = resposta.message ?? 'Análise gerada. Revise antes de utilizar.';
            aviso.hidden = false;
            regiao.querySelector('[data-gerar-analise-botao]').focus();
        } catch (erro) {
            if (atual.signal.aborted || controle !== atual || !dialogo.open) {
                return;
            }
            status.hidden = true;
            erroLocal.textContent = erro.status ? erro.message
                : 'Não foi possível confirmar a geração. Feche e abra a modal para conferir a análise antes de tentar novamente.';
            erroLocal.hidden = false;
        } finally {
            botao.disabled = false;
            botao.textContent = textoOriginal;
            regiao.removeAttribute('aria-busy');
            geracoesPendentes.delete(formulario);
        }
    }

    async function carregar() {
        controle?.abort();
        const atual = new AbortController();
        controle = atual;
        conteudo.replaceChildren();
        conteudo.setAttribute('aria-busy', 'true');
        carregando.hidden = false;
        falha.hidden = true;
        mensagem.textContent = '';

        try {
            const dados = await solicitar(destino, { signal: atual.signal, base: doc.baseURI });
            if (atual.signal.aborted || !dialogo.open) {
                return;
            }
            if (dados.redirect !== null || Object.keys(dados.data ?? {}).length !== 0
                || Object.keys(dados.fragments).length !== 1 || typeof dados.fragments['visualizacao-candidato'] !== 'string') {
                throw new ErroAjax('O servidor enviou uma resposta inesperada.');
            }
            conteudo.innerHTML = dados.fragments['visualizacao-candidato'];
        } catch (erro) {
            if (atual.signal.aborted || !dialogo.open) {
                return;
            }
            mensagem.textContent = [401, 403, 404, 419].includes(erro.status)
                ? erro.message : 'Não foi possível carregar o currículo e a análise. Tente novamente.';
            falha.hidden = false;
        } finally {
            if (controle === atual) {
                carregando.hidden = true;
                conteudo.removeAttribute('aria-busy');
            }
        }
    }

    dialogo.addEventListener('close', () => {
        controle?.abort();
        controle = null;
        destino = null;
        conteudo.replaceChildren();
        conteudo.removeAttribute('aria-busy');
        nome.textContent = '';
        mensagem.textContent = '';
        carregando.hidden = true;
        falha.hidden = true;
    });

    doc.addEventListener('submit', (evento) => {
        const formulario = evento.target;
        if (!formulario.matches?.('form[data-gerar-analise-modal]') || !dialogo.contains(formulario) || !dialogo.open) {
            return;
        }
        evento.preventDefault();
        return gerarAnalise(formulario);
    });

    doc.addEventListener('click', (evento) => {
        if (evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) {
            return;
        }
        const botao = evento.target.closest?.('[data-visualizar-candidato]');
        if (botao && doc.getElementById('quadro-vaga')?.contains(botao)) {
            evento.preventDefault();
            destino = botao.dataset.visualizacaoUrl;
            nome.textContent = botao.closest('[data-codigo]').querySelector('[data-link-perfil]').textContent;
            dialogo.showModal();
            return carregar();
        }
        if (evento.target.closest?.('[data-tentar-visualizacao]') && dialogo.contains(evento.target) && dialogo.open && destino) {
            evento.preventDefault();
            return carregar();
        }
    });
}

if (typeof document !== 'undefined') {
    registrarVisualizacaoCandidato();
}
