import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { registrarVisualizacaoCandidato } from '../../resources/js/modulos/visualizacao-candidato.js';

const fetchOriginal = globalThis.fetch;
const formDataOriginal = globalThis.FormData;
afterEach(() => { globalThis.fetch = fetchOriginal; globalThis.FormData = formDataOriginal; });

const envelope = (html) => ({ message: null, redirect: null, fragments: { 'visualizacao-candidato': html } });
const resposta = (dados, status = 200) => new Response(JSON.stringify(dados), { status, headers: { 'Content-Type': 'application/json' } });

function montar() {
    const eventos = {};
    const elementos = Object.fromEntries(['nome', 'carregando', 'falha', 'erro'].map((chave) => [chave, { hidden: true, textContent: '' }]));
    const botaoGerar = { disabled: false, textContent: 'Gerar análise de IA', focus() { this.focado = true; } };
    const statusAnalise = { hidden: true, textContent: '' };
    const erroAnalise = { hidden: true, textContent: '' };
    const energia = {
        innerHTML: 'análise anterior', atributos: {},
        querySelector: (seletor) => ({ '[data-analise-status]': statusAnalise, '[data-analise-erro]': erroAnalise, '[data-gerar-analise-botao]': botaoGerar })[seletor],
        setAttribute(chave, valor) { this.atributos[chave] = valor; },
        removeAttribute(chave) { delete this.atributos[chave]; },
    };
    const conteudo = {
        innerHTML: '', atributos: {},
        replaceChildren() { this.innerHTML = ''; },
        setAttribute(chave, valor) { this.atributos[chave] = valor; },
        removeAttribute(chave) { delete this.atributos[chave]; },
        querySelector: (seletor) => seletor === '#visualizacao-energia' ? energia : null,
    };
    const dialogo = {
        open: false,
        querySelector: (seletor) => elementos[seletor.match(/data-visualizacao-(.+)\]/)[1]],
        addEventListener: (tipo, acao) => { eventos[`dialogo:${tipo}`] = acao; },
        showModal() { this.open = true; },
        close() { this.open = false; eventos['dialogo:close'](); },
        contains: (alvo) => alvo.noDialogo === true,
    };
    const doc = {
        baseURI: 'https://banco.test/vagas/3',
        getElementById: (id) => ({
            'curriculo-analise-quadro': dialogo,
            'visualizacao-candidato': conteudo,
            'quadro-vaga': { contains: (alvo) => alvo.noQuadro === true },
        })[id],
        addEventListener: (tipo, acao) => { eventos[tipo] = acao; },
    };
    registrarVisualizacaoCandidato(doc);

    function clicar(nome = 'Ana Silva', codigo = 'CAND-0042', opcoes = {}) {
        const botao = {
            noQuadro: opcoes.noQuadro ?? true,
            dataset: { visualizacaoUrl: `/vagas/3/candidatos/${codigo}/visualizacao` },
            closest: () => ({ querySelector: () => ({ textContent: nome }) }),
        };
        const evento = {
            button: 0, ...opcoes, prevenido: false,
            target: { closest: (seletor) => seletor === '[data-visualizar-candidato]' ? botao : null },
            preventDefault() { this.prevenido = true; },
        };
        return { evento, promessa: eventos.click(evento) };
    }

    function tentarNovamente() {
        return eventos.click({
            button: 0,
            target: { noDialogo: true, closest: (seletor) => seletor === '[data-tentar-visualizacao]' ? {} : null },
            preventDefault() {},
        });
    }

    const formulario = {
        noDialogo: true, action: '/vagas/3/candidatos/CAND-0042/energia',
        matches: (seletor) => seletor === 'form[data-gerar-analise-modal]',
        querySelector: () => botaoGerar,
    };
    function gerar() {
        globalThis.FormData = class { constructor() { return new formDataOriginal(); } };
        return eventos.submit({ target: formulario, preventDefault() {} });
    }

    return { ...elementos, conteudo, dialogo, clicar, tentarNovamente, gerar, botaoGerar, statusAnalise, erroAnalise, energia };
}

test('abre a modal com o candidato e carrega PDF e análise por GET sem cache', async () => {
    const tela = montar();
    let liberar;
    let chamada;
    globalThis.fetch = (url, opcoes) => {
        chamada = { url: String(url), opcoes };
        return new Promise((resolve) => { liberar = () => resolve(resposta(envelope('<iframe></iframe><p>Análise salva</p>'))); });
    };

    const clique = tela.clicar('<script>Ana</script>');
    assert.equal(tela.dialogo.open, true);
    assert.equal(tela.nome.textContent, '<script>Ana</script>');
    assert.equal(tela.nome.innerHTML, undefined);
    assert.equal(tela.carregando.hidden, false);
    assert.equal(tela.conteudo.atributos['aria-busy'], 'true');
    liberar();
    await clique.promessa;

    assert.equal(clique.evento.prevenido, true);
    assert.equal(chamada.url, 'https://banco.test/vagas/3/candidatos/CAND-0042/visualizacao');
    assert.equal(chamada.opcoes.method, 'GET');
    assert.equal(chamada.opcoes.cache, 'no-store');
    assert.equal(chamada.opcoes.credentials, 'same-origin');
    assert.equal(tela.conteudo.innerHTML, '<iframe></iframe><p>Análise salva</p>');
    assert.equal(tela.carregando.hidden, true);
    assert.equal(tela.conteudo.atributos['aria-busy'], undefined);
});

test('fechar remove PDF e dados pessoais e impede resposta atrasada de repovoar a modal', async () => {
    const tela = montar();
    let liberar;
    let signal;
    globalThis.fetch = (url, opcoes) => {
        signal = opcoes.signal;
        return new Promise((resolve) => { liberar = () => resolve(resposta(envelope('dados antigos'))); });
    };
    const clique = tela.clicar();

    tela.dialogo.close();
    liberar();
    await clique.promessa;

    assert.equal(signal.aborted, true);
    assert.equal(tela.conteudo.innerHTML, '');
    assert.equal(tela.nome.textContent, '');
    assert.equal(tela.falha.hidden, true);
});

test('trocar candidato limpa o conteúdo anterior e ignora respostas fora de ordem', async () => {
    const tela = montar();
    let liberar;
    globalThis.fetch = () => new Promise((resolve) => { liberar = () => resolve(resposta(envelope('Ana'))); });
    const primeiro = tela.clicar();
    globalThis.fetch = async () => resposta(envelope('Bruno'));

    await tela.clicar('Bruno', 'CAND-0043').promessa;
    liberar();
    await primeiro.promessa;

    assert.equal(tela.nome.textContent, 'Bruno');
    assert.equal(tela.conteudo.innerHTML, 'Bruno');
    tela.dialogo.close();
    assert.equal(tela.conteudo.innerHTML, '');
});

test('falha mantém a modal aberta com erro local e permite tentar novamente', async () => {
    const tela = montar();
    globalThis.fetch = async () => { throw new TypeError('offline'); };

    await tela.clicar().promessa;

    assert.equal(tela.dialogo.open, true);
    assert.equal(tela.falha.hidden, false);
    assert.match(tela.erro.textContent, /Não foi possível carregar/);
    assert.equal(tela.carregando.hidden, true);
    globalThis.fetch = async () => resposta(envelope('nova tentativa'));
    await tela.tentarNovamente();
    assert.equal(tela.conteudo.innerHTML, 'nova tentativa');
    assert.equal(tela.falha.hidden, true);
});

test('recusa fragmentos de outras regiões, redirecionamentos e dados do quadro', async () => {
    const tela = montar();
    for (const dados of [
        { message: null, redirect: null, fragments: { 'perfil-na-vaga': 'outro perfil' } },
        { ...envelope('perfil'), redirect: '/login' },
        { ...envelope('perfil'), data: { 'quadro-vaga': {} } },
        { ...envelope('perfil'), fragments: { 'visualizacao-candidato': 'perfil', 'quadro-vaga': 'quadro' } },
    ]) {
        globalThis.fetch = async () => resposta(dados);
        await tela.clicar().promessa;
        assert.equal(tela.conteudo.innerHTML, '');
        assert.equal(tela.falha.hidden, false);
    }
});

test('sessão expirada e acesso recusado exibem mensagens próprias sem carregar conteúdo', async () => {
    const tela = montar();
    for (const [status, mensagem] of [[401, /sessão expirou/], [403, /permissão/], [404, /não está mais disponível/], [419, /token expirou/]]) {
        globalThis.fetch = async () => resposta({ message: 'erro' }, status);
        await tela.clicar().promessa;
        assert.match(tela.erro.textContent, mensagem);
        assert.equal(tela.conteudo.innerHTML, '');
    }
});

test('ignora cliques fora do quadro ou com modificadores', async () => {
    const tela = montar();
    globalThis.fetch = async () => { assert.fail('Não deveria consultar o servidor'); };
    for (const opcoes of [{ noQuadro: false }, { ctrlKey: true }, { button: 1 }]) {
        const clique = tela.clicar('Ana', 'CAND-0042', opcoes);
        await clique.promessa;
        assert.equal(clique.evento.prevenido, false);
    }
    assert.equal(tela.dialogo.open, false);
});

test('geração envia POST, bloqueia cliques repetidos e atualiza apenas a análise sem recarregar PDF', async () => {
    const tela = montar();
    globalThis.fetch = async () => resposta(envelope('<iframe>PDF original</iframe>'));
    await tela.clicar().promessa;
    let liberar;
    const chamadas = [];
    globalThis.fetch = (url, opcoes) => {
        chamadas.push({ url: String(url), opcoes });
        return new Promise((resolve) => { liberar = () => resolve(resposta({ message: 'Análise gerada.', redirect: null, fragments: { 'visualizacao-energia': 'novo perfil de energia' } })); });
    };

    const envio = tela.gerar();
    await tela.gerar();
    assert.equal(tela.botaoGerar.disabled, true);
    assert.equal(tela.botaoGerar.textContent, 'Gerando análise…');
    assert.equal(tela.energia.atributos['aria-busy'], 'true');
    assert.equal(tela.statusAnalise.hidden, false);
    assert.equal(chamadas.length, 1);
    liberar();
    await envio;

    assert.equal(chamadas[0].opcoes.method, 'POST');
    assert.equal(chamadas[0].url, 'https://banco.test/vagas/3/candidatos/CAND-0042/energia');
    assert.ok(chamadas[0].opcoes.body instanceof formDataOriginal);
    assert.equal(tela.energia.innerHTML, 'novo perfil de energia');
    assert.equal(tela.conteudo.innerHTML, '<iframe>PDF original</iframe>');
    assert.equal(tela.statusAnalise.textContent, 'Análise gerada.');
    assert.equal(tela.botaoGerar.disabled, false);
    assert.equal(tela.botaoGerar.focado, true);
    assert.equal(tela.dialogo.open, true);
});

test('IA indisponível mantém a análise anterior e o PDF e libera o botão sem repetir envio', async () => {
    const tela = montar();
    globalThis.fetch = async () => resposta(envelope('PDF mantido'));
    await tela.clicar().promessa;
    let chamadas = 0;
    globalThis.fetch = async () => { chamadas++; return resposta({ message: 'indisponível' }, 503); };

    await tela.gerar();

    assert.equal(chamadas, 1);
    assert.equal(tela.energia.innerHTML, 'análise anterior');
    assert.equal(tela.conteudo.innerHTML, 'PDF mantido');
    assert.equal(tela.erroAnalise.textContent, 'Análise indisponível no momento.');
    assert.equal(tela.erroAnalise.hidden, false);
    assert.equal(tela.statusAnalise.hidden, true);
    assert.equal(tela.botaoGerar.disabled, false);
    assert.equal(tela.botaoGerar.textContent, 'Gerar análise de IA');
});

test('falha de rede na geração pede conferência antes de tentar novamente e não reenvia', async () => {
    const tela = montar();
    globalThis.fetch = async () => resposta(envelope('PDF'));
    await tela.clicar().promessa;
    let chamadas = 0;
    globalThis.fetch = async () => { chamadas++; throw new TypeError('offline'); };

    await tela.gerar();

    assert.equal(chamadas, 1);
    assert.match(tela.erroAnalise.textContent, /conferir a análise antes de tentar novamente/);
    assert.equal(tela.energia.innerHTML, 'análise anterior');
});

test('resposta de geração após fechar e abrir outro candidato não altera a nova modal', async () => {
    const tela = montar();
    globalThis.fetch = async () => resposta(envelope('PDF da Ana'));
    await tela.clicar().promessa;
    let liberar;
    globalThis.fetch = () => new Promise((resolve) => { liberar = () => resolve(resposta({ message: null, redirect: null, fragments: { 'visualizacao-energia': 'análise da Ana' } })); });
    const envio = tela.gerar();
    tela.dialogo.close();
    globalThis.fetch = async () => resposta(envelope('PDF do Bruno'));
    await tela.clicar('Bruno', 'CAND-0043').promessa;

    liberar();
    await envio;

    assert.equal(tela.conteudo.innerHTML, 'PDF do Bruno');
    assert.equal(tela.nome.textContent, 'Bruno');
    assert.notEqual(tela.energia.innerHTML, 'análise da Ana');
});

test('geração recusa resposta de outra região sem apagar a análise anterior', async () => {
    const tela = montar();
    globalThis.fetch = async () => resposta(envelope('PDF'));
    await tela.clicar().promessa;
    globalThis.fetch = async () => resposta(envelope('outro candidato'));

    await tela.gerar();

    assert.equal(tela.energia.innerHTML, 'análise anterior');
    assert.equal(tela.erroAnalise.hidden, false);
    assert.equal(tela.botaoGerar.disabled, false);
});
