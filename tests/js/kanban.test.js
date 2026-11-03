import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { enviarFormulario } from '../../resources/js/modulos/formularios.js';
import { registrarQuadro } from '../../resources/js/modulos/kanban.js';
import { registrarRenderizador } from '../../resources/js/modulos/ajax.js';

const fetchOriginal = globalThis.fetch;
const formDataOriginal = globalThis.FormData;
const confirmOriginal = globalThis.confirm;
afterEach(() => {
    globalThis.fetch = fetchOriginal;
    globalThis.FormData = formDataOriginal;
    globalThis.confirm = confirmOriginal;
});

function resposta(dados, status = 200) {
    return new Response(JSON.stringify(dados), { status, headers: { 'Content-Type': 'application/json' } });
}

const envelope = (fragments, message = null) => ({ message, fragments, redirect: null });

function montarQuadro() {
    const docEventos = {};
    const atributos = new Map();
    const historicoBusy = [];
    const aviso = { textContent: '' };
    const login = { hidden: true };
    const focados = [];
    const regiao = {
        innerHTML: 'quadro anterior',
        setAttribute: (chave, valor) => { atributos.set(chave, valor); historicoBusy.push(valor); },
        removeAttribute: (chave) => atributos.delete(chave),
        contains: (elemento) => elemento.dentroDoQuadro === true,
        querySelector: (seletor) => seletor === '[data-atualizar-quadro]' ? { focus: () => focados.push('atualizar') } : null,
    };
    const doc = {
        baseURI: 'https://banco.test/vagas/3',
        getElementById: (id) => id === 'quadro-vaga' ? regiao : null,
        querySelector: (seletor) => ({ '[data-ajax-anuncios]': aviso, '[data-ajax-login]': login })[seletor] ?? null,
        addEventListener: (nome, fn) => { docEventos[nome] = fn; },
    };
    registrarQuadro(doc);

    function clicar({ dentroDoQuadro = true, ...opcoes } = {}) {
        const link = { href: 'https://banco.test/vagas/3', dentroDoQuadro };
        const evento = {
            button: 0, ...opcoes, prevenido: false,
            target: { closest: (seletor) => seletor === '[data-atualizar-quadro]' ? link : null },
            preventDefault() { this.prevenido = true; },
        };
        return { evento, promessa: docEventos.click(evento) };
    }

    return { regiao, atributos, historicoBusy, aviso, login, focados, clicar };
}

test('Atualizar quadro refaz a leitura por GET e substitui só a região do quadro', async () => {
    const quadro = montarQuadro();
    const chamadas = [];
    globalThis.fetch = async (url, opcoes) => {
        chamadas.push({ url: String(url), opcoes });
        return resposta(envelope({ 'quadro-vaga': 'quadro novo' }));
    };

    const { evento, promessa } = quadro.clicar();
    await promessa;

    assert.equal(evento.prevenido, true);
    assert.equal(chamadas.length, 1);
    assert.equal(chamadas[0].url, 'https://banco.test/vagas/3');
    assert.equal(chamadas[0].opcoes.method, 'GET');
    assert.equal(chamadas[0].opcoes.headers.Accept, 'application/json');
    assert.equal(chamadas[0].opcoes.body, undefined);
    assert.equal(quadro.regiao.innerHTML, 'quadro novo');
    assert.deepEqual(quadro.historicoBusy, ['true']);
    assert.equal(quadro.atributos.has('aria-busy'), false);
    assert.equal(quadro.aviso.textContent, 'Quadro atualizado.');
    assert.deepEqual(quadro.focados, ['atualizar']);
});

test('cliques repetidos enquanto a leitura está pendente geram uma única requisição', async () => {
    const quadro = montarQuadro();
    let liberar;
    let chamadas = 0;
    globalThis.fetch = () => {
        chamadas++;
        return new Promise((resolve) => { liberar = () => resolve(resposta(envelope({ 'quadro-vaga': 'quadro novo' }))); });
    };

    const primeiro = quadro.clicar();
    const segundo = quadro.clicar();
    await segundo.promessa;
    assert.equal(segundo.evento.prevenido, true);
    liberar();
    await primeiro.promessa;

    assert.equal(chamadas, 1);
    assert.equal(quadro.regiao.innerHTML, 'quadro novo');

    globalThis.fetch = async () => { chamadas++; return resposta(envelope({ 'quadro-vaga': 'terceiro' })); };
    await quadro.clicar().promessa;
    assert.equal(chamadas, 2);
});

test('falha mantém o quadro anterior, libera nova tentativa e avisa em português', async () => {
    const quadro = montarQuadro();
    globalThis.fetch = async () => { throw new TypeError('Failed to fetch'); };

    await quadro.clicar().promessa;

    assert.equal(quadro.regiao.innerHTML, 'quadro anterior');
    assert.equal(quadro.atributos.has('aria-busy'), false);
    assert.match(quadro.aviso.textContent, /Não foi possível atualizar o quadro/);

    globalThis.fetch = async () => resposta({ message: 'Sua sessão expirou. Entre novamente para continuar.' }, 401);
    await quadro.clicar().promessa;
    assert.equal(quadro.regiao.innerHTML, 'quadro anterior');
    assert.equal(quadro.login.hidden, false);
    assert.match(quadro.aviso.textContent, /sessão expirou/);
});

test('resposta com outra região, redirect ou HTML é recusada sem alterar o quadro', async () => {
    const quadro = montarQuadro();
    const respostas = [
        resposta(envelope({ 'resultados-candidatos': 'lista' })),
        resposta(envelope({ 'quadro-vaga': 'a', 'perfil-na-vaga': 'b' })),
        resposta({ message: null, fragments: { 'quadro-vaga': 'a' }, redirect: 'https://banco.test/login' }),
        new Response('<html>login</html>', { status: 200, headers: { 'Content-Type': 'text/html' } }),
    ];

    for (const atual of respostas) {
        globalThis.fetch = async () => atual;
        await quadro.clicar().promessa;
        assert.equal(quadro.regiao.innerHTML, 'quadro anterior');
    }
});

test('cliques com modificador ou fora do quadro seguem a navegação normal', async () => {
    const quadro = montarQuadro();
    let chamadas = 0;
    globalThis.fetch = async () => { chamadas++; return resposta(envelope({ 'quadro-vaga': 'x' })); };

    const comCtrl = quadro.clicar({ ctrlKey: true });
    const foraDoQuadro = quadro.clicar({ dentroDoQuadro: false });
    await Promise.all([comCtrl.promessa, foraDoQuadro.promessa]);

    assert.equal(comCtrl.evento.prevenido, false);
    assert.equal(foraDoQuadro.evento.prevenido, false);
    assert.equal(chamadas, 0);
});

test('Encerrar pelo quadro substitui a região e leva o foco ao ponto indicado pelo novo HTML', async () => {
    let conectado = true;
    const focados = [];
    const aviso = { textContent: '' };
    const regiao = {
        html: 'quadro com o formulário de encerrar',
        get innerHTML() { return this.html; },
        set innerHTML(html) { this.html = html; conectado = false; },
        querySelector: (seletor) => seletor === '[data-foco-apos-ajax]' ? { focus: () => focados.push('titulo') } : null,
    };
    const doc = {
        baseURI: 'https://banco.test/vagas/3',
        getElementById: (id) => id === 'quadro-vaga' ? regiao : null,
        querySelector: (seletor) => seletor === '[data-ajax-anuncios]' ? aviso : null,
    };
    const botao = { disabled: false, textContent: 'Encerrar vaga', matches: () => true, childNodes: [], replaceChildren() {} };
    const form = {
        method: 'post', action: 'https://banco.test/vagas/3/status', dataset: { confirmar: 'Encerrar a vaga?' },
        elements: [botao], ownerDocument: doc,
        get isConnected() { return conectado; },
        setAttribute() {}, removeAttribute() {}, querySelectorAll: () => [], querySelector: () => null,
        dispatchEvent: () => true,
    };
    globalThis.FormData = class { constructor() { return new formDataOriginal(); } };
    let chamadas = 0;
    globalThis.fetch = async () => {
        chamadas++;
        return resposta(envelope({ 'quadro-vaga': 'quadro encerrado' }, 'Vaga encerrada. O quadro fica disponível para consulta.'));
    };

    await enviarFormulario(form, botao, () => false);
    assert.equal(chamadas, 0);

    await enviarFormulario(form, botao, () => true);

    assert.equal(chamadas, 1);
    assert.equal(regiao.innerHTML, 'quadro encerrado');
    assert.equal(aviso.textContent, 'Vaga encerrada. O quadro fica disponível para consulta.');
    assert.deepEqual(focados, ['titulo']);
});

function montarMovimentacao() {
    const docEventos = {};
    const focados = [];
    const aviso = { textContent: '' };
    const atributos = new Map();
    const alvos = new Set();
    let html = 'quadro anterior';
    let versao = 0;
    const regiao = {
        id: 'quadro-vaga',
        get innerHTML() { return html; },
        set innerHTML(novo) { html = novo; versao++; },
        setAttribute: (chave, valor) => atributos.set(chave, valor),
        removeAttribute: (chave) => atributos.delete(chave),
        contains: (elemento) => elemento?.dentroDoQuadro === true,
        querySelectorAll: (seletor) => seletor === '[data-codigo]'
            ? [{ dataset: { codigo: 'CAND-0042' }, querySelector: () => ({ focus: () => focados.push('CAND-0042') }) }]
            : [],
        querySelector: () => null,
    };
    const coluna = (etapa) => ({
        dentroDoQuadro: true, dataset: { etapa },
        setAttribute: () => alvos.add(etapa), removeAttribute: () => alvos.delete(etapa),
    });
    const colunas = { triagem: coluna('triagem'), entrevista: coluna('entrevista'), aprovado: coluna('aprovado') };
    const doc = {
        baseURI: 'https://banco.test/vagas/3',
        getElementById: (id) => id === 'quadro-vaga' ? regiao : null,
        querySelector: (seletor) => seletor === '[data-ajax-anuncios]' ? aviso : null,
        querySelectorAll: () => [...alvos].map((etapa) => colunas[etapa]),
        addEventListener: (nome, fn) => { docEventos[nome] = fn; },
        createElement: () => ({ dataset: {}, setAttribute() {}, focus() {} }),
    };
    function formulario(codigo = 'CAND-0042', etapa = 'triagem') {
        const versaoInicial = versao;
        const form = {
            method: 'post', action: `https://banco.test/vagas/3/candidatos/${codigo}/etapa`, dataset: {},
            elements: [], ownerDocument: doc, dentroDoQuadro: true,
            get isConnected() { return versao === versaoInicial; },
            matches: (seletor) => seletor === 'form[data-mover-etapa]',
            closest: (seletor) => ({ '#quadro-vaga, #perfil-na-vaga': regiao, '[data-codigo]': card })[seletor] ?? null,
            setAttribute() {}, removeAttribute() {}, querySelectorAll: () => [], querySelector: () => null,
            dispatchEvent: () => true, prepend() {},
        };
        const card = {
            dentroDoQuadro: true, dataset: { codigo },
            querySelector: (seletor) => seletor === 'form[data-mover-etapa]' ? form : null,
            closest: (seletor) => seletor === '[data-etapa]' ? colunas[etapa] : null,
        };
        return { form, card };
    }
    function evento(target, extra = {}) {
        return { target, prevenido: false, preventDefault() { this.prevenido = true; }, ...extra };
    }
    const elementoEm = (alvo) => ({
        closest: (seletor) => {
            if (seletor === '[data-etapa]') {
                return alvo.dataset?.etapa ? alvo : (alvo.closest?.('[data-etapa]') ?? null);
            }
            return seletor === '[draggable="true"][data-codigo]' && alvo.dataset?.codigo ? alvo : null;
        },
    });
    registrarQuadro(doc);
    globalThis.FormData = class { constructor() { return new formDataOriginal(); } };
    return { doc, docEventos, regiao, atributos, alvos, colunas, aviso, focados, formulario, evento, elementoEm };
}

function montarExpansao(quadro, identificacao, dentroDoQuadro = true) {
    const detalhes = { hidden: true };
    const atributos = new Map([['aria-expanded', 'false']]);
    const card = {
        querySelector: (seletor) => ({
            '[data-card-detalhes]': detalhes,
            '[data-link-perfil]': { textContent: identificacao },
        })[seletor],
    };
    const botao = {
        dentroDoQuadro,
        closest: (seletor) => seletor === '[data-codigo]' ? card : null,
        getAttribute: (nome) => atributos.get(nome),
        setAttribute: (nome, valor) => atributos.set(nome, valor),
    };
    const icone = { closest: (seletor) => seletor === '[data-expandir-card]' ? botao : null };
    return { detalhes, atributos, clicar: () => quadro.docEventos.click(quadro.evento(icone, { button: 0 })) };
}

test('seta expande e recolhe somente as competências do card escolhido sem requisições', async () => {
    const quadro = montarMovimentacao();
    const primeiro = montarExpansao(quadro, 'CAND-0042');
    const segundo = montarExpansao(quadro, 'Ana Silva');
    let chamadas = 0;
    globalThis.fetch = async () => { chamadas++; throw new Error('Expansão não deve acessar o servidor'); };

    await primeiro.clicar();

    assert.equal(primeiro.detalhes.hidden, false);
    assert.equal(primeiro.atributos.get('aria-expanded'), 'true');
    assert.equal(primeiro.atributos.get('aria-label'), 'Ocultar competências de CAND-0042');
    assert.equal(segundo.detalhes.hidden, true);

    await segundo.clicar();
    await primeiro.clicar();

    assert.equal(primeiro.detalhes.hidden, true);
    assert.equal(primeiro.atributos.get('aria-expanded'), 'false');
    assert.equal(primeiro.atributos.get('aria-label'), 'Mostrar competências de CAND-0042');
    assert.equal(segundo.detalhes.hidden, false);
    assert.equal(segundo.atributos.get('aria-label'), 'Ocultar competências de Ana Silva');
    assert.equal(chamadas, 0);
});

test('expansão ignora controles fora do quadro e funciona em cards inseridos após atualizar', async () => {
    const quadro = montarMovimentacao();
    const externo = montarExpansao(quadro, 'Externo', false);

    await externo.clicar();

    assert.equal(externo.detalhes.hidden, true);
    assert.equal(externo.atributos.get('aria-expanded'), 'false');

    quadro.regiao.innerHTML = 'quadro atualizado';
    const novo = montarExpansao(quadro, 'CAND-0043');
    await novo.clicar();

    assert.equal(novo.detalhes.hidden, false);
    assert.equal(novo.atributos.get('aria-expanded'), 'true');
});

function instalarAnimacao(quadro, expansao, movimentoReduzido = false) {
    const animacoes = [];
    const natural = { height: '180px', paddingTop: '12px', borderTopWidth: '1px', marginBottom: '0px', opacity: '1' };
    let estiloAtual = natural;
    quadro.doc.defaultView = {
        matchMedia: () => ({ matches: movimentoReduzido }),
        getComputedStyle: (elemento) => elemento === expansao.detalhes ? estiloAtual : { rowGap: '12px' },
    };
    expansao.detalhes.animate = (quadros, opcoes) => {
        const animacao = {
            quadros, opcoes, cancelada: false,
            cancel() { this.cancelada = true; estiloAtual = natural; },
            concluir() { this.onfinish(); },
        };
        animacoes.push(animacao);
        return animacao;
    };
    return { animacoes, emProgresso: (altura) => { estiloAtual = { ...natural, height: altura }; } };
}

test('competências crescem gradualmente e só ficam ocultas ao terminar o recolhimento', async () => {
    const quadro = montarMovimentacao();
    const expansao = montarExpansao(quadro, 'CAND-0042');
    const { animacoes } = instalarAnimacao(quadro, expansao);

    await expansao.clicar();

    assert.equal(animacoes[0].quadros[0].height, '0px');
    assert.equal(animacoes[0].quadros[0].marginBottom, '-12px');
    assert.equal(animacoes[0].quadros[1].height, '180px');
    assert.ok(animacoes[0].opcoes.duration > 0);
    animacoes[0].concluir();
    assert.equal(expansao.detalhes.hidden, false);
    assert.equal(animacoes[0].cancelada, true);

    await expansao.clicar();

    assert.equal(expansao.detalhes.hidden, false);
    assert.equal(expansao.atributos.get('aria-expanded'), 'false');
    assert.equal(animacoes[1].quadros[0].height, '180px');
    assert.equal(animacoes[1].quadros[1].height, '0px');
    animacoes[1].concluir();
    assert.equal(expansao.detalhes.hidden, true);
    assert.equal(animacoes[1].cancelada, true);
});

test('cliques rápidos invertem a animação da altura atual sem ocultar um card reaberto', async () => {
    const quadro = montarMovimentacao();
    const expansao = montarExpansao(quadro, 'CAND-0042');
    const { animacoes, emProgresso } = instalarAnimacao(quadro, expansao);
    await expansao.clicar();
    emProgresso('70px');

    await expansao.clicar();

    assert.equal(animacoes[0].cancelada, true);
    assert.equal(animacoes[1].quadros[0].height, '70px');
    emProgresso('40px');

    await expansao.clicar();
    animacoes[1].concluir();

    assert.equal(animacoes[1].cancelada, true);
    assert.equal(animacoes[2].quadros[0].height, '40px');
    assert.equal(animacoes[2].quadros[1].height, '180px');
    assert.equal(expansao.detalhes.hidden, false);
    assert.equal(expansao.atributos.get('aria-expanded'), 'true');
    animacoes[2].concluir();
    assert.equal(expansao.detalhes.hidden, false);
});

test('preferência por movimento reduzido mantém expansão e recolhimento sem animação', async () => {
    const quadro = montarMovimentacao();
    const expansao = montarExpansao(quadro, 'CAND-0042');
    const { animacoes } = instalarAnimacao(quadro, expansao, true);

    await expansao.clicar();
    assert.equal(expansao.detalhes.hidden, false);
    await expansao.clicar();

    assert.equal(expansao.detalhes.hidden, true);
    assert.equal(expansao.atributos.get('aria-expanded'), 'false');
    assert.deepEqual(animacoes, []);
});

test('cada movimentação fecha a mensagem anterior antes de enviar: o nome não sobra ao voltar à triagem', async () => {
    const quadro = montarMovimentacao();
    const ordem = [];
    const alertaComNome = {
        querySelector: (seletor) => seletor === '[data-alerta-fechar]' ? { click: () => ordem.push('fechou') } : null,
        remove: () => ordem.push('removeu'),
    };
    quadro.doc.querySelectorAll = (seletor) => seletor === '[data-alerta-ativo][data-alerta-tipo="sucesso"]' ? [alertaComNome] : [];
    globalThis.fetch = async () => {
        ordem.push('enviou');
        return resposta(envelope({ 'quadro-vaga': 'quadro novo' }, 'CAND-0042 está agora em Triagem.'));
    };

    await quadro.docEventos.submit(quadro.evento(quadro.formulario().form, { submitter: { name: 'etapa', value: 'triagem' } }));

    // Sai do documento antes do envio, sem esperar o fade: nada do nome pode restar quando a resposta chegar.
    assert.deepEqual(ordem, ['fechou', 'removeu', 'enviou']);
});

test('mover por botão bloqueia o quadro enquanto pendente e só aplica o HTML do servidor', async () => {
    const quadro = montarMovimentacao();
    const chamadas = [];
    let liberar;
    globalThis.fetch = (url, opcoes) => {
        chamadas.push({ url: String(url), opcoes });
        return new Promise((resolve) => {
            liberar = () => resolve(resposta(envelope({ 'quadro-vaga': 'quadro novo' }, 'CAND-0042 está agora em Entrevista.')));
        });
    };
    const primeiro = quadro.formulario();
    const segundo = quadro.formulario('CAND-0077');

    const envio = quadro.evento(primeiro.form, { submitter: { name: 'etapa', value: 'entrevista' } });
    const pendente = quadro.docEventos.submit(envio);
    const repetido = quadro.evento(segundo.form, { submitter: { name: 'etapa', value: 'entrevista' } });
    await quadro.docEventos.submit(repetido);

    assert.equal(envio.prevenido, true);
    assert.equal(repetido.prevenido, true);
    assert.equal(chamadas.length, 1);
    assert.equal(quadro.atributos.get('aria-busy'), 'true');
    assert.equal(quadro.regiao.innerHTML, 'quadro anterior');

    liberar();
    await pendente;

    assert.equal(chamadas[0].opcoes.method, 'POST');
    assert.equal(chamadas[0].opcoes.body.get('etapa'), 'entrevista');
    assert.equal(quadro.regiao.innerHTML, 'quadro novo');
    assert.equal(quadro.atributos.has('aria-busy'), false);
    assert.equal(quadro.aviso.textContent, 'CAND-0042 está agora em Entrevista.');
    assert.deepEqual(quadro.focados, ['CAND-0042']);
});

test('falha na movimentação não move o card e libera o quadro', async () => {
    const quadro = montarMovimentacao();
    globalThis.fetch = async () => resposta({ message: 'Revise.', errors: { etapa: ['Vaga encerrada — somente consulta. Não é possível mover candidatos.'] } }, 422);

    await quadro.docEventos.submit(quadro.evento(quadro.formulario().form, { submitter: { name: 'etapa', value: 'entrevista' } }));

    assert.equal(quadro.regiao.innerHTML, 'quadro anterior');
    assert.equal(quadro.atributos.has('aria-busy'), false);
    assert.match(quadro.aviso.textContent, /Vaga encerrada/);

    let chamadas = 0;
    globalThis.fetch = async () => { chamadas++; return resposta(envelope({ 'quadro-vaga': 'quadro novo' })); };
    await quadro.docEventos.submit(quadro.evento(quadro.formulario().form, { submitter: { name: 'etapa', value: 'entrevista' } }));
    assert.equal(chamadas, 1);
});

test('arrastar para outra coluna envia o mesmo formulário com a etapa de destino', async () => {
    const quadro = montarMovimentacao();
    const corpos = [];
    globalThis.fetch = async (url, opcoes) => {
        corpos.push(opcoes.body);
        return resposta(envelope({ 'quadro-vaga': 'quadro novo' }));
    };
    const { card } = quadro.formulario('CAND-0042', 'triagem');
    const transferencia = { dados: {}, setData(tipo, valor) { this.dados[tipo] = valor; } };

    quadro.docEventos.dragstart(quadro.evento(quadro.elementoEm(card), { dataTransfer: transferencia }));
    const mesmaColuna = quadro.evento(quadro.elementoEm(quadro.colunas.triagem));
    quadro.docEventos.dragover(mesmaColuna);
    const sobreEntrevista = quadro.evento(quadro.elementoEm(quadro.colunas.entrevista));
    quadro.docEventos.dragover(sobreEntrevista);

    assert.equal(transferencia.dados['text/plain'], 'CAND-0042');
    assert.equal(mesmaColuna.prevenido, false);
    assert.equal(sobreEntrevista.prevenido, true);
    assert.deepEqual([...quadro.alvos], ['entrevista']);

    await quadro.docEventos.drop(quadro.evento(quadro.elementoEm(quadro.colunas.entrevista)));

    assert.equal(corpos.length, 1);
    assert.equal(corpos[0].get('etapa'), 'entrevista');
    assert.equal(quadro.regiao.innerHTML, 'quadro novo');
    assert.deepEqual([...quadro.alvos], []);
});

test('soltar na própria coluna ou sem arrastar um card não envia nada', async () => {
    const quadro = montarMovimentacao();
    let chamadas = 0;
    globalThis.fetch = async () => { chamadas++; return resposta(envelope({ 'quadro-vaga': 'x' })); };
    const { card } = quadro.formulario('CAND-0042', 'entrevista');

    const semArrasto = quadro.evento(quadro.elementoEm(quadro.colunas.aprovado));
    await quadro.docEventos.drop(semArrasto);
    quadro.docEventos.dragstart(quadro.evento(quadro.elementoEm(card), { dataTransfer: { setData() {} } }));
    const mesma = quadro.evento(quadro.elementoEm(quadro.colunas.entrevista));
    await quadro.docEventos.drop(mesma);
    quadro.docEventos.dragend(quadro.evento(quadro.elementoEm(card)));

    assert.equal(semArrasto.prevenido, false);
    assert.equal(mesma.prevenido, false);
    assert.equal(chamadas, 0);
    assert.equal(quadro.regiao.innerHTML, 'quadro anterior');
});

test('Atualizar quadro aceita dados JSON e monta a região no JavaScript, sem HTML do servidor', async () => {
    const quadro = montarQuadro();
    const dados = { vaga: { titulo: 'Atualizada' } };
    registrarRenderizador('quadro-vaga', (recebido) => {
        assert.deepEqual(recebido, dados);
        return { textContent: recebido.vaga.titulo };
    });
    quadro.regiao.replaceChildren = (novo) => { quadro.regiao.innerHTML = novo.textContent; };
    globalThis.fetch = async () => resposta({ ...envelope({}), data: { 'quadro-vaga': dados } });

    await quadro.clicar().promessa;

    assert.equal(quadro.regiao.innerHTML, 'Atualizada');
    assert.deepEqual(quadro.focados, ['atualizar']);
});

test('encerramento usa uma confirmação e impede movimentações e atualização concorrentes', async () => {
    const quadro = montarMovimentacao();
    const { form } = quadro.formulario();
    form.matches = (seletor) => seletor === 'form[data-encerrar-vaga]';
    form.dataset.confirmar = 'Encerrar a vaga?';
    let confirmacoes = 0;
    globalThis.confirm = () => { confirmacoes++; return true; };
    let chamadas = 0;
    let liberar;
    globalThis.fetch = () => {
        chamadas++;
        return new Promise((resolve) => { liberar = () => resolve(resposta({ ...envelope({}), data: { 'quadro-vaga': { encerrada: true } } })); });
    };
    registrarRenderizador('quadro-vaga', () => 'encerrada');
    quadro.regiao.replaceChildren = (novo) => { quadro.regiao.innerHTML = novo; };

    const envio = quadro.docEventos.submit(quadro.evento(form));
    await quadro.docEventos.submit(quadro.evento(quadro.formulario('CAND-0077').form, { submitter: { name: 'etapa', value: 'entrevista' } }));
    await quadro.docEventos.click(quadro.evento({ closest: (seletor) => seletor === '[data-atualizar-quadro]' ? { dentroDoQuadro: true } : null }, { button: 0 }));
    assert.equal(chamadas, 1);
    liberar();
    await envio;

    assert.equal(confirmacoes, 1);
    assert.equal(quadro.regiao.innerHTML, 'encerrada');
    assert.equal(quadro.atributos.has('aria-busy'), false);
});
