import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { configurarBusca } from '../../resources/js/modulos/busca.js';

const fetchOriginal = globalThis.fetch;
const formDataOriginal = globalThis.FormData;
afterEach(() => {
    globalThis.fetch = fetchOriginal;
    globalThis.FormData = formDataOriginal;
});
const esperar = () => new Promise(setImmediate);

function montar(t) {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const eventos = {};
    const docEventos = {};
    const janelaEventos = {};
    const atributos = new Map();
    const aviso = { textContent: '' };
    const resumo = { textContent: '', hidden: true, focado: false, focus() { this.focado = true; } };
    const regiao = {
        innerHTML: 'resultado anterior', setAttribute: (k, v) => atributos.set(k, v),
        contains: () => false,
        removeAttribute: (k) => atributos.delete(k),
        querySelector: () => ({ textContent: '13 talentos encontrados', focus() {} }),
    };
    function campo(name, type, value = '') {
        return {
            name, type, value, checked: false, selectionStart: 2, selectionEnd: 2,
            closest: () => null,
            matches: () => ['text', 'search', 'textarea'].includes(type),
        };
    }
    const busca = campo('busca', 'search', 'Ana');
    const tipo = campo('tipo', 'select-one', 'estagiario');
    const situacao = { ...campo('situacao', 'select-one', 'ativos'), dataset: { valorPadrao: 'ativos' } };
    const experiencia = campo('experiencia', 'checkbox', '1');
    const competencia = campo('competencias[]', 'checkbox', '7');
    const semNome = campo('', 'search', 'Python');
    const doc = {
        baseURI: 'https://banco.test/candidatos?busca=Ana&tipo=estagiario&page=2', activeElement: busca,
        getElementById: (id) => id === 'resultados-candidatos' ? regiao : null,
        querySelector: () => aviso,
        addEventListener: (nome, fn) => { docEventos[nome] = fn; },
    };
    const form = {
        action: 'https://banco.test/candidatos', elements: [busca, tipo, situacao, experiencia, competencia, semNome],
        ownerDocument: doc, dataset: {},
        addEventListener: (nome, fn) => { eventos[nome] = (evento) => fn({ type: nome, ...evento }); },
        querySelectorAll: () => [], querySelector: () => resumo,
    };
    globalThis.FormData = class {
        constructor(formulario) {
            const dados = new formDataOriginal();
            for (const campo of formulario.elements) {
                if (campo.name && (campo.type !== 'checkbox' || campo.checked)) {
                    dados.append(campo.name, campo.value);
                }
            }
            return dados;
        }
    };
    const location = { href: doc.baseURI };
    const historico = [];
    const janela = {
        location,
        history: Object.fromEntries(['replaceState', 'pushState'].map((metodo) => [metodo, (estado, titulo, url) => {
            historico.push([metodo, String(url)]);
            location.href = String(url);
        }])),
        addEventListener: (nome, fn) => { janelaEventos[nome] = fn; },
    };
    const chamadas = [];
    globalThis.fetch = (url, options) => new Promise((resolve, reject) => {
        chamadas.push({ url, options, resolve, reject });
    });
    configurarBusca(form, janela);
    function resolver(indice, texto = 'resultado novo') {
        chamadas[indice].resolve(new Response(JSON.stringify({
            message: null, fragments: { 'resultados-candidatos': texto }, redirect: null,
        }), { headers: { 'Content-Type': 'application/json' } }));
    }
    function clicar(href, limpar = false) {
        const link = {
            href, hasAttribute: (nome) => limpar && nome === 'data-limpar-busca',
            closest: (seletor) => seletor === '#resultados-candidatos' ? regiao : form,
        };
        return docEventos.click({ button: 0, target: { closest: () => link }, preventDefault() {} });
    }
    return { eventos, janelaEventos, doc, form, busca, tipo, situacao, experiencia, competencia, regiao, aviso, resumo, atributos, chamadas, resolver, janela, historico, clicar };
}

test('debounce de 400 ms, reset de page e URL confirmada somente no sucesso sem perder campo/foco/cursor', async (t) => {
    const ui = montar(t);
    ui.busca.value = 'Ana Li';
    ui.eventos.input({ target: ui.busca });
    t.mock.timers.tick(399);
    assert.equal(ui.chamadas.length, 0);
    t.mock.timers.tick(1);
    assert.equal(ui.chamadas.length, 1);
    assert.equal(ui.chamadas[0].url.searchParams.has('page'), false);
    assert.equal(ui.historico.length, 0);
    ui.resolver(0);
    await esperar();
    assert.equal(ui.regiao.innerHTML, 'resultado novo');
    assert.equal(ui.historico.at(-1)[0], 'replaceState');
    assert.match(ui.janela.location.href, /busca=Ana\+Li/);
    assert.equal(ui.doc.activeElement, ui.busca);
    assert.equal(ui.busca.value, 'Ana Li');
    assert.equal(ui.busca.selectionStart, 2);
    assert.equal(ui.atributos.has('aria-busy'), false);
});

test('situação usa a busca existente, mantém URL e volta a Ativos ao limpar ou voltar à URL sem filtro', async (t) => {
    const ui = montar(t);
    ui.situacao.value = 'retencao';
    ui.eventos.change({ target: ui.situacao });
    assert.equal(ui.chamadas.length, 1);
    assert.equal(ui.chamadas[0].url.searchParams.get('situacao'), 'retencao');
    ui.resolver(0);
    await esperar();
    assert.match(ui.janela.location.href, /situacao=retencao/);

    ui.clicar('https://banco.test/candidatos', true);
    ui.resolver(1);
    await esperar();
    assert.equal(ui.situacao.value, 'ativos');

    ui.situacao.value = 'desativados';
    ui.janela.location.href = 'https://banco.test/candidatos';
    ui.janelaEventos.popstate();
    ui.resolver(2);
    await esperar();
    assert.equal(ui.situacao.value, 'ativos');
});

test('edição invalida e cancela GET antes do debounce e descarta respostas fora de ordem', async (t) => {
    const ui = montar(t);
    ui.eventos.change({ target: ui.tipo });
    assert.equal(ui.chamadas.length, 1);
    ui.busca.value = 'Marina';
    ui.eventos.input({ target: ui.busca });
    assert.equal(ui.chamadas[0].options.signal.aborted, true);
    ui.resolver(0, 'obsoleto durante debounce');
    await esperar();
    assert.equal(ui.regiao.innerHTML, 'resultado anterior');
    assert.equal(ui.historico.length, 0);
    t.mock.timers.tick(400);
    ui.eventos.change({ target: ui.experiencia });
    ui.resolver(2, 'última intenção');
    await esperar();
    ui.resolver(1, 'fora de ordem');
    await esperar();
    assert.equal(ui.regiao.innerHTML, 'última intenção');
    assert.equal(ui.historico.length, 1);
});

test('falha preserva resultados e URL e libera aria-busy', async (t) => {
    const ui = montar(t);
    const anterior = ui.janela.location.href;
    ui.eventos.change({ target: ui.tipo });
    ui.chamadas[0].reject(new TypeError('offline'));
    await esperar();
    assert.equal(ui.regiao.innerHTML, 'resultado anterior');
    assert.equal(ui.janela.location.href, anterior);
    assert.equal(ui.atributos.has('aria-busy'), false);
    assert.ok(ui.aviso.textContent.length > 0);
    assert.equal(ui.resumo.focado, false);
    assert.equal(ui.doc.activeElement, ui.busca);
});

test('falha no popstate volta à última URL confirmada e mantém o resultado anterior', async (t) => {
    const ui = montar(t);
    const anterior = ui.janela.location.href;
    ui.janela.location.href = 'https://banco.test/candidatos?busca=Falha';
    ui.janelaEventos.popstate();
    assert.equal(ui.busca.value, 'Falha');
    ui.chamadas[0].reject(new TypeError('offline'));
    await esperar();
    assert.equal(ui.janela.location.href, anterior);
    assert.equal(ui.busca.value, 'Ana');
    assert.equal(ui.regiao.innerHTML, 'resultado anterior');
});

test('resposta com região de outra operação não altera resultados nem URL', async (t) => {
    const ui = montar(t);
    const anterior = ui.janela.location.href;
    ui.eventos.change({ target: ui.tipo });
    ui.chamadas[0].resolve(new Response(JSON.stringify({
        message: null, fragments: { 'quadro-vaga': 'HTML de outro fluxo' }, redirect: null,
    }), { headers: { 'Content-Type': 'application/json' } }));
    await esperar();
    assert.equal(ui.regiao.innerHTML, 'resultado anterior');
    assert.equal(ui.janela.location.href, anterior);
    assert.match(ui.aviso.textContent, /inesperada/);
});

test('paginação usa pushState; popstate restaura campos e resultado sem criar entrada', async (t) => {
    const ui = montar(t);
    ui.clicar('https://banco.test/candidatos?busca=Ana&tipo=estagiario&page=3');
    ui.resolver(0, 'página 3');
    await esperar();
    assert.equal(ui.historico.at(-1)[0], 'pushState');
    assert.equal(ui.chamadas[0].url.searchParams.get('page'), '3');
    ui.janela.location.href = 'https://banco.test/candidatos?busca=Maria&competencias%5B%5D=7&experiencia=1';
    ui.janelaEventos.popstate();
    assert.equal(ui.busca.value, 'Maria');
    assert.equal(ui.tipo.value, '');
    assert.equal(ui.competencia.checked, true);
    assert.equal(ui.experiencia.checked, true);
    ui.resolver(1, 'resultado restaurado');
    await esperar();
    assert.equal(ui.regiao.innerHTML, 'resultado restaurado');
    assert.equal(ui.historico.length, 1);
});

test('limpar é delegado, não repete autoenvio e limpa campos só na confirmação da consulta', async (t) => {
    const ui = montar(t);
    ui.clicar('https://banco.test/candidatos', true);
    assert.equal(ui.busca.value, 'Ana');
    ui.resolver(0);
    await esperar();
    assert.equal(ui.busca.value, '');
    assert.equal(ui.tipo.value, '');
    assert.equal(ui.historico.at(-1)[0], 'replaceState');
    assert.equal(ui.chamadas.length, 1);
});

test('regressão: substituir os resultados mantém paginação e limpar delegados, sem listeners novos', async (t) => {
    const ui = montar(t);
    for (let pagina = 2; pagina <= 4; pagina++) {
        ui.clicar(`https://banco.test/candidatos?tipo=estagiario&page=${pagina}`);
        ui.resolver(pagina - 2, `página ${pagina}`);
        await esperar();
        assert.equal(ui.regiao.innerHTML, `página ${pagina}`);
    }
    assert.equal(ui.chamadas.length, 3);
    assert.deepEqual(ui.historico.map(([metodo]) => metodo), ['pushState', 'pushState', 'pushState']);
    ui.clicar('https://banco.test/candidatos', true);
    ui.resolver(3, 'sem filtros');
    await esperar();
    assert.equal(ui.chamadas.length, 4);
    assert.equal(ui.regiao.innerHTML, 'sem filtros');
});

test('regressão: resposta obsoleta de paginação não sobrescreve busca mais nova nem a URL', async (t) => {
    const ui = montar(t);
    ui.clicar('https://banco.test/candidatos?tipo=estagiario&page=2');
    ui.eventos.change({ target: ui.tipo });
    assert.equal(ui.chamadas[0].options.signal.aborted, true);
    ui.resolver(1, 'filtro novo');
    await esperar();
    ui.resolver(0, 'página antiga');
    await esperar();
    assert.equal(ui.regiao.innerHTML, 'filtro novo');
    assert.deepEqual(ui.historico.map(([metodo]) => metodo), ['replaceState']);
    assert.doesNotMatch(ui.janela.location.href, /page=/);
});

test('regressão: falha seguida de nova edição recupera a busca sem repetir a requisição que falhou', async (t) => {
    const ui = montar(t);
    ui.eventos.change({ target: ui.tipo });
    ui.chamadas[0].reject(new TypeError('offline'));
    await esperar();
    assert.equal(ui.chamadas.length, 1);
    assert.equal(ui.regiao.innerHTML, 'resultado anterior');

    ui.eventos.change({ target: ui.experiencia });
    assert.equal(ui.chamadas.length, 2);
    ui.resolver(1, 'recuperado');
    await esperar();
    assert.equal(ui.regiao.innerHTML, 'recuperado');
    assert.equal(ui.atributos.has('aria-busy'), false);
});

test('regressão: erros 401 e 419 na busca não inserem página nem mexem nos resultados', async (t) => {
    for (const status of [401, 419]) {
        const ui = montar(t);
        const anterior = ui.janela.location.href;
        ui.eventos.change({ target: ui.tipo });
        ui.chamadas[0].resolve(new Response(JSON.stringify({ message: 'x' }), { status, headers: { 'Content-Type': 'application/json' } }));
        await esperar();
        assert.equal(ui.regiao.innerHTML, 'resultado anterior');
        assert.equal(ui.janela.location.href, anterior);
        assert.match(ui.aviso.textContent, /sessão/i);
        t.mock.timers.reset();
    }
});
