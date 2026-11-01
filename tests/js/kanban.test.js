import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { enviarFormulario } from '../../resources/js/modulos/formularios.js';
import { registrarQuadro } from '../../resources/js/modulos/kanban.js';

const fetchOriginal = globalThis.fetch;
const formDataOriginal = globalThis.FormData;
afterEach(() => {
    globalThis.fetch = fetchOriginal;
    globalThis.FormData = formDataOriginal;
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
